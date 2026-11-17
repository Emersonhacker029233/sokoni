<?php

namespace Tests\Feature\Returns;

use App\Filament\Resources\Reports\Pages\ViewReport;
use App\Filament\Resources\ReturnRequests\Pages\ListReturnRequests;
use App\Filament\Resources\ReturnRequests\Pages\ViewReturnRequest;
use App\Models\Report;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Return photos and comments are user content: the shop can report them,
 * moderators can hide them, and the existing block rules apply. Plus the
 * admin Returns queue.
 */
class ReturnModerationTest extends TestCase
{
    use RefreshDatabase;
    use ReturnsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReturns();
    }

    public function test_the_shop_can_report_a_return_and_moderators_see_its_photos_and_comment(): void
    {
        $return = $this->requestedReturn();
        $this->asSeller($return)->getJson("/api/returns/{$return->id}")->assertJsonPath('data.actions.can_report', true);

        $this->asSeller($return)->postJson('/api/reports', [
            'reportable_type' => 'return_request', 'reportable_id' => $return->id, 'reason' => 'abusive', 'note' => 'Insulting comment',
        ])->assertCreated();

        $report = Report::query()->sole();
        $this->assertSame(ReturnRequest::class, $report->reportable_type);
        $this->assertSame($return->buyer_id, $report->offendingUser()->id);
        $preview = $report->contentPreview();
        $this->assertStringContainsString($return->order->code, $preview['title']);
        $this->assertSame('It is the blue one, I ordered black.', $preview['text']);
        $this->assertCount(2, $preview['images']);
        $this->assertTrue($report->canHideContent());

        $admin = User::factory()->admin()->create();
        $this->actingAsAdmin($admin)->get("/admin/reports/{$report->id}")->assertOk()->assertSee('Return request for order');
    }

    public function test_only_the_shop_of_a_return_can_report_it(): void
    {
        $return = $this->requestedReturn();
        $body = ['reportable_type' => 'return_request', 'reportable_id' => $return->id, 'reason' => 'spam'];

        $this->asBuyer($return)->postJson('/api/reports', $body)->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson('/api/reports', $body)->assertUnprocessable()->assertJsonValidationErrors('reportable_id');
        $this->assertSame(0, Report::query()->count());
    }

    public function test_hidden_content_disappears_for_the_shop(): void
    {
        $return = $this->requestedReturn();
        $this->asSeller($return)->postJson('/api/reports', ['reportable_type' => 'return_request', 'reportable_id' => $return->id, 'reason' => 'abusive'])->assertCreated();
        $admin = User::factory()->admin()->create();
        $this->actingAsAdmin($admin);
        Livewire::test(ViewReport::class, ['record' => Report::query()->sole()->id])->callAction('hide')->assertHasNoActionErrors();

        $this->assertTrue($return->fresh()->is_hidden);
        $this->asSeller($return)->getJson("/api/returns/{$return->id}")
            ->assertJsonPath('data.comment', null)
            ->assertJsonPath('data.photos', [])
            ->assertJsonPath('data.content_hidden', true)
            ->assertJsonPath('data.actions.can_accept', true, 'the decision itself goes on');
    }

    public function test_a_buyer_who_blocked_the_shop_cannot_request_until_they_unblock(): void
    {
        $order = $this->deliveredOrder();
        UserBlock::query()->create(['blocker_id' => $order->buyer_id, 'blocked_id' => $order->seller->user_id]);

        $this->requestReturn($order)->assertForbidden()->assertJsonPath('code', 'blocked');
    }

    public function test_a_shop_that_blocked_the_buyer_cannot_dodge_the_return_sokoni_decides(): void
    {
        $order = $this->deliveredOrder();
        $seller = $order->seller->user;
        UserBlock::query()->create(['blocker_id' => $seller->id, 'blocked_id' => $order->buyer_id]);

        $id = $this->requestReturn($order)->assertCreated()->json('data.id');
        $return = ReturnRequest::query()->findOrFail($id);

        $this->assertSame('seller_blocked_buyer', $return->escalation_reason);
        $this->assertTrue($return->needsAdmin());
        $this->assertCount(0, $this->pushes->to($seller)->filter(fn ($p) => ($p['data']['type'] ?? null) === 'return_status'), 'the shop hears nothing from someone it blocked');
        $this->actingAs($seller)->getJson("/api/returns/{$id}")
            ->assertJsonPath('data.comment', null)
            ->assertJsonPath('data.photos', [])
            ->assertJsonPath('data.handled_by_support', true)
            ->assertJsonPath('data.actions.can_accept', false);
        $this->actingAs($seller)->postJson("/api/returns/{$id}/accept")->assertStatus(409)->assertJsonPath('code', 'handled_by_support');
    }

    public function test_the_admin_returns_queue_puts_returns_needing_sokoni_first_and_can_decide(): void
    {
        $admin = User::factory()->admin()->create();
        $quiet = $this->requestedReturn();
        $disputed = $this->requestedReturn('damaged');
        $this->asSeller($disputed)->postJson("/api/returns/{$disputed->id}/reject", ['reason' => 'No damage in the photos'])->assertOk();
        $this->asBuyer($disputed)->postJson("/api/returns/{$disputed->id}/dispute", ['comment' => 'Look at the corner'])->assertOk();

        $this->actingAsAdmin($admin)->get('/admin/returns')->assertOk();
        $this->actingAsAdmin($admin)->get("/admin/returns/{$disputed->id}")->assertOk()->assertSee('Look at the corner');

        $this->actingAsAdmin($admin);
        Livewire::test(ListReturnRequests::class)
            ->filterTable('needs_admin', true)
            ->assertCanSeeTableRecords([$disputed])
            ->assertCanNotSeeTableRecords([$quiet]);

        Livewire::test(ViewReturnRequest::class, ['record' => $disputed->id])
            ->callAction('closeReturn', data: ['note' => 'Photos show no damage; rejection upheld'])
            ->assertHasNoActionErrors();
        $this->assertSame('closed', $disputed->fresh()->status);
        $this->assertSame('Photos show no damage; rejection upheld', $disputed->fresh()->resolution_note);

        Livewire::test(ViewReturnRequest::class, ['record' => $quiet->id])
            ->callAction('setPayer', data: ['payer' => 'buyer'])
            ->assertHasNoActionErrors()
            ->callAction('acceptReturn', data: ['note' => 'Accepted by Sokoni'])
            ->assertHasNoActionErrors();
        $quiet->refresh();
        $this->assertSame('accepted', $quiet->status);
        $this->assertSame('buyer', $quiet->payer);
        $this->assertSame('unpaid', $quiet->fee_status);
    }
}
