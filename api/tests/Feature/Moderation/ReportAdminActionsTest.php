<?php

namespace Tests\Feature\Moderation;

use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reports\Pages\ViewReport;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Report;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Apple Guideline 1.2: "act on objectionable content reports within 24
 * hours by removing the content and ejecting the user who provided it".
 */
class ReportAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->admin()->create();
    }

    private function reportOn($item, string $reason = 'abusive'): Report
    {
        return Report::query()->create([
            'reporter_id' => User::factory()->create()->id,
            'reportable_type' => $item::class,
            'reportable_id' => $item->id,
            'reason' => $reason,
        ]);
    }

    public function test_remove_content_and_ban_user_ejects_the_author_and_everything_they_posted(): void
    {
        $author = User::factory()->create();
        $shop = SellerProfile::factory()->verified()->create(['user_id' => $author->id]);
        $product = Product::factory()->create(['seller_id' => $shop->id]);
        $otherProduct = Product::factory()->create(['seller_id' => $shop->id]);
        $author->createToken('phone');
        $report = $this->reportOn($product);

        Livewire::actingAs($this->admin)
            ->test(ListReports::class)
            ->callTableAction('removeAndBan', $report, data: ['reason' => 'Sexual content'])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($product->fresh()->is_hidden);
        $this->assertTrue($author->fresh()->isBanned());
        $this->assertSame('Sexual content', $author->fresh()->ban_reason);
        $this->assertSame(0, $author->tokens()->count());

        $report->refresh();
        $this->assertSame('upheld', $report->status);
        $this->assertSame($this->admin->id, $report->resolved_by);
        $this->assertNotNull($report->resolved_at);

        // The rest of what they posted is gone from the public too.
        $ids = collect($this->getJson('/api/products')->json('data'))->pluck('id');
        $this->assertNotContains($otherProduct->id, $ids);
    }

    public function test_a_reported_chat_message_can_now_be_hidden(): void
    {
        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $conversation->buyer_id]);
        $report = $this->reportOn($message);

        Livewire::actingAs($this->admin)
            ->test(ListReports::class)
            ->callTableAction('hide', $report)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($message->fresh()->is_hidden);
        $this->assertSame('upheld', $report->fresh()->status);
    }

    public function test_a_reported_shop_can_be_hidden_and_its_logo_removed(): void
    {
        $shop = SellerProfile::factory()->verified()->create(['logo' => 'https://example.com/logo.png']);
        $report = $this->reportOn($shop);

        Livewire::actingAs($this->admin)
            ->test(ListReports::class)
            ->callTableAction('removeLogo', $report)
            ->callTableAction('hide', $report->fresh());

        $this->assertNull($shop->fresh()->logo);
        $this->assertTrue($shop->fresh()->is_hidden);
    }

    public function test_the_overdue_filter_shows_only_reports_past_24_hours(): void
    {
        $old = $this->reportOn(Comment::factory()->create());
        $this->travel(25)->hours();
        $fresh = $this->reportOn(Comment::factory()->create());

        Livewire::actingAs($this->admin)
            ->test(ListReports::class)
            ->filterTable('overdue')
            ->assertCanSeeTableRecords([$old])
            ->assertCanNotSeeTableRecords([$fresh]);
    }

    public function test_pending_reports_are_listed_oldest_first_with_the_overdue_one_marked(): void
    {
        $old = $this->reportOn(Comment::factory()->create(['body' => 'old insult']));
        $this->travel(25)->hours();
        $fresh = $this->reportOn(Comment::factory()->create(['body' => 'new insult']));
        $done = $this->reportOn(Comment::factory()->create());
        $done->forceFill(['status' => 'dismissed'])->save();

        Livewire::actingAs($this->admin)
            ->test(ListReports::class)
            ->assertCanSeeTableRecords([$old, $fresh, $done], inOrder: true)
            ->assertSee('Overdue by');
    }

    public function test_the_report_page_shows_the_reported_content_itself(): void
    {
        $comment = Comment::factory()->create(['body' => 'This exact abusive sentence']);
        $report = $this->reportOn($comment);

        $this->actingAsAdmin($this->admin)
            ->get('/admin/reports/'.$report->id)
            ->assertOk()
            ->assertSee('This exact abusive sentence')
            ->assertSee('Remove content and ban user');
    }

    public function test_an_admin_account_is_never_banned_from_a_report(): void
    {
        $otherAdmin = User::factory()->admin()->create();
        $report = $this->reportOn($otherAdmin);

        Livewire::actingAs($this->admin)
            ->test(ViewReport::class, ['record' => $report->getRouteKey()])
            ->assertActionHidden('removeAndBan')
            ->assertActionHidden('ban');
    }
}
