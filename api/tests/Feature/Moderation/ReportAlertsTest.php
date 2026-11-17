<?php

namespace Tests\Feature\Moderation;

use App\Models\Comment;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\Showcase;
use App\Models\Update;
use App\Models\User;
use App\Notifications\NewReportNotification;
use App\Notifications\WaitingReportsSummaryNotification;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Apple Guideline 1.2: a way to flag any objectionable content, and the
 * developer acting on reports within 24 hours. Every new report emails
 * MODERATION_ALERT_EMAIL and rings the admin panel's bell; a daily
 * summary lists every report still pending after 20 hours.
 */
class ReportAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sokoni.moderation_alert_email' => 'moderation@sokoni.co.tz, owner@sokoni.co.tz']);
    }

    /** @return array<string, array{string, callable}> */
    public static function newTypes(): array
    {
        return [
            'review' => ['review', function () {
                $order = Order::factory()->completed()->create();

                return Review::factory()->create(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id]);
            }],
            'showcase' => ['showcase', fn () => Showcase::factory()->create()],
            'update' => ['update', fn () => Update::factory()->create()],
            'offer' => ['offer', fn () => Offer::factory()->create()],
            'user' => ['user', fn () => User::factory()->create()],
            'comment' => ['comment', fn () => Comment::factory()->create()],
        ];
    }

    #[DataProvider('newTypes')]
    public function test_every_kind_of_user_content_can_be_reported(string $type, callable $make): void
    {
        Notification::fake();
        $item = $make();
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->postJson('/api/reports', [
            'reportable_type' => $type,
            'reportable_id' => $item->id,
            'reason' => 'abusive',
        ])->assertCreated();

        $report = Report::query()->sole();
        $this->assertSame($item::class, $report->reportable_type);
        $this->assertSame('Offensive or abusive', $report->reasonLabel());
        $this->assertNotNull($report->offendingUser(), 'Moderators must be able to act on the author');
    }

    public function test_a_new_report_emails_the_moderators_and_rings_the_admin_bell(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['title' => 'Bad listing']);

        $this->actingAs(User::factory()->create())->postJson('/api/reports', [
            'reportable_type' => 'product',
            'reportable_id' => $product->id,
            'reason' => 'sexual_violent',
            'note' => 'See the second photo',
        ])->assertCreated();

        Notification::assertSentOnDemand(
            NewReportNotification::class,
            function (NewReportNotification $notification, array $channels, $notifiable) {
                $mail = $notification->toMail($notifiable);

                return $notifiable->routes['mail'] === ['moderation@sokoni.co.tz', 'owner@sokoni.co.tz']
                    && str_contains($mail->subject, 'Sexual or violent content')
                    && collect($mail->introLines)->contains(fn ($line) => str_contains($line, 'See the second photo'));
            },
        );
        Notification::assertSentTo($admin, DatabaseNotification::class);
        $this->assertNotNull(Report::query()->sole()->alerted_at);
    }

    public function test_the_admin_bell_is_stored_in_the_notifications_table(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs(User::factory()->create())->postJson('/api/reports', [
            'reportable_type' => 'product',
            'reportable_id' => Product::factory()->create()->id,
            'reason' => 'spam',
        ])->assertCreated();

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame('New report', $admin->notifications()->first()->data['title']);
    }

    public function test_reporting_the_same_thing_again_while_pending_files_and_alerts_once(): void
    {
        Notification::fake();
        $reporter = User::factory()->create();
        $product = Product::factory()->create();
        $payload = ['reportable_type' => 'product', 'reportable_id' => $product->id, 'reason' => 'spam'];

        $this->actingAs($reporter)->postJson('/api/reports', $payload)->assertCreated();
        $this->actingAs($reporter)->postJson('/api/reports', $payload)->assertCreated();

        $this->assertSame(1, Report::query()->count());
        Notification::assertSentOnDemandTimes(NewReportNotification::class, 1);
    }

    public function test_an_old_app_reason_label_is_stored_and_shown_as_sent(): void
    {
        Notification::fake();

        $this->actingAs(User::factory()->create())->postJson('/api/reports', [
            'reportable_type' => 'product',
            'reportable_id' => Product::factory()->create()->id,
            'reason' => 'Si maudhui ya biashara',
        ])->assertCreated();

        $this->assertSame('Si maudhui ya biashara', Report::query()->sole()->reasonLabel());
    }

    public function test_the_24_hour_clock(): void
    {
        Notification::fake();
        $report = Report::factory()->create(['status' => 'pending']);

        $this->assertFalse($report->isOverdue());
        $this->assertSame('success', $report->dueColor());

        $this->travel(19)->hours();
        $this->assertSame('warning', $report->fresh()->dueColor());

        $this->travel(6)->hours();
        $this->assertTrue($report->fresh()->isOverdue());
        $this->assertStringStartsWith('Overdue by', $report->fresh()->dueLabel());
        $this->assertSame([$report->id], Report::query()->overdue()->pluck('id')->all());
    }

    public function test_the_daily_summary_lists_reports_waiting_over_20_hours_only(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $old = Report::factory()->create(['status' => 'pending']);
        $this->travel(21)->hours();
        Report::factory()->create(['status' => 'pending']);
        Report::factory()->create(['status' => 'dismissed']);

        $this->artisan('reports:waiting-summary')->assertSuccessful();

        Notification::assertSentOnDemand(
            WaitingReportsSummaryNotification::class,
            function (WaitingReportsSummaryNotification $notification, array $channels, $notifiable) use ($old) {
                $lines = implode("\n", $notification->toMail($notifiable)->introLines);

                return str_contains($lines, '#'.$old->id) && substr_count($lines, '**#') === 1;
            },
        );
        Notification::assertSentTo($admin, DatabaseNotification::class);
    }

    public function test_the_daily_summary_sends_nothing_when_no_report_is_waiting(): void
    {
        Notification::fake();
        Report::factory()->create(['status' => 'pending']);

        $this->artisan('reports:waiting-summary')->assertSuccessful();

        Notification::assertSentOnDemandTimes(WaitingReportsSummaryNotification::class, 0);
    }

    public function test_the_daily_summary_is_scheduled(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'reports:waiting-summary'));

        $this->assertCount(1, $events);
        $this->assertSame('0 5 * * *', $events->first()->expression);
    }

    public function test_a_report_is_still_filed_when_no_alert_address_is_valid(): void
    {
        Notification::fake();
        config(['sokoni.moderation_alert_email' => 'not-an-email']);

        $this->actingAs(User::factory()->create())->postJson('/api/reports', [
            'reportable_type' => 'product',
            'reportable_id' => Product::factory()->create()->id,
            'reason' => 'spam',
        ])->assertCreated();

        $this->assertSame(1, Report::query()->count());
        Notification::assertSentOnDemandTimes(NewReportNotification::class, 0);
    }
}
