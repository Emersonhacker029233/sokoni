<?php

namespace App\Http\Resources\Returns;

use App\Http\Resources\Delivery\DeliveryJobResource;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Services\Delivery\DeliveryPaymentService;
use App\Services\Returns\ReturnService;
use App\Support\PrivateFileUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One return, for its buyer or its shop (docs/delivery/RETURNS.md).
 *
 * The buyer's comment and photos are user content: hidden from the shop
 * when moderators hid them (Report > Hide content) or when the shop has
 * blocked the buyer (then Sokoni support handles it). Photos are 30-minute
 * signed links to the private disk.
 *
 * @mixin ReturnRequest
 */
class ReturnRequestResource extends JsonResource
{
    public const BUYER = 'buyer';

    public const SELLER = 'seller';

    private string $viewer = self::BUYER;

    public static function for(ReturnRequest $return, string $viewer): self
    {
        $resource = new self($return);
        $resource->viewer = $viewer;

        return $resource;
    }

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['order', 'photos', 'payments']);
        $service = app(ReturnService::class);
        $user = $request->user();
        $isBuyer = $this->viewer === self::BUYER;
        $supportHandles = ! $isBuyer && $user !== null && $service->hiddenFromSeller($this->resource, $user);
        $showContent = ! $this->is_hidden && ! $supportHandles;
        $driver = (array) ($this->provider_driver ?? []);
        $rideActive = $this->isRideActive();

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_code' => (string) $this->order->code,
            'status' => $this->status,
            'reason' => $this->reason,
            'reason_label' => __("returns.reasons.{$this->reason}"),
            'comment' => $showContent ? $this->comment : null,
            'photos' => $showContent ? $this->photos->map(fn ($p) => ['id' => $p->id, 'url' => PrivateFileUrl::for($p->path)])->values()->all() : [],
            'content_hidden' => ! $showContent,
            'handled_by_support' => $supportHandles || $this->needsAdmin(),
            'payer' => $this->payer,
            'fee' => $this->fee,
            'fee_status' => $this->fee_status,
            'currency' => $this->currency ?? 'TZS',
            'seller_deadline' => $this->status === 'requested' ? $this->sellerDeadline() : null,
            'escalated' => $this->escalated_at !== null,
            'decision' => $this->decided_at === null ? null : [
                'by' => $this->decided_role,
                'at' => $this->decided_at,
                'note' => $this->decision_note,
            ],
            'dispute' => $this->disputed_at === null ? null : ['at' => $this->disputed_at, 'comment' => $this->dispute_comment],
            'resolution_note' => $this->resolution_note,
            'ride' => $this->ride_status === null ? null : [
                'status' => $this->ride_status,
                'failure_reason' => $this->ride_failure_reason,
                'estimate' => $this->estimate_max === null ? null : ['min' => $this->estimate_min, 'max' => $this->estimate_max],
                'driver' => ($driver['name'] ?? null) === null ? null : [
                    'name' => $driver['name'],
                    'phone' => $rideActive ? ($driver['phone'] ?? null) : null,
                    'plate' => $driver['plate'] ?? null,
                    'model' => $driver['model'] ?? null,
                    'photo_url' => $driver['photo_url'] ?? null,
                ],
                'tracking_url' => $rideActive ? ($driver['tracking_url'] ?? null) : null,
            ],
            'payment' => $isBuyer && $this->payer === 'buyer' ? $this->paymentView() : null,
            'received_at' => $this->received_at,
            'refunded_at' => $this->refunded_at,
            'closed_at' => $this->closed_at,
            'closed_reason' => $this->closed_reason,
            'created_at' => $this->created_at,
            'actions' => $isBuyer ? $this->buyerActions() : $this->sellerActions($supportHandles),
        ];
    }

    private function buyerActions(): array
    {
        $payments = app(DeliveryPaymentService::class);

        return [
            'can_dispute' => $this->status === 'rejected',
            'can_withdraw' => in_array($this->status, ['requested', 'rejected', 'disputed', 'accepted'], true) && ! $this->isRideActive(),
            'can_pay' => $this->status === 'accepted' && $this->payer === 'buyer' && $this->fee_status === 'unpaid'
                && (int) $this->fee > 0 && $payments->onlinePaymentAvailable(),
            'can_book_ride' => $this->status === 'accepted' && $this->feeSettled() && ! $this->isRideActive()
                && $this->ride_status !== 'unavailable',
            'can_report' => false,
        ];
    }

    private function sellerActions(bool $supportHandles): array
    {
        return [
            'can_accept' => ! $supportHandles && $this->status === 'requested',
            'can_reject' => ! $supportHandles && $this->status === 'requested',
            'can_confirm_refund' => ! $supportHandles && in_array($this->status, ['accepted', 'return_in_transit', 'received'], true),
            'can_report' => ! $supportHandles && ! $this->is_hidden,
        ];
    }

    private function paymentView(): array
    {
        $latest = $this->payments->sortByDesc('id')->first();
        $paid = $this->payments->where('status', 'succeeded')->sortByDesc('id')->first();
        $capabilities = app(DeliveryPaymentService::class)->capabilities();

        return [
            'amount_due' => $this->status === 'accepted' && $this->fee_status === 'unpaid' ? (int) $this->fee : 0,
            'online_available' => $capabilities->collections,
            'sandbox' => $capabilities->sandbox,
            'channels' => $capabilities->channels,
            'attempt' => $latest instanceof Payment ? DeliveryJobResource::attemptArray($latest) : null,
            'receipt' => $paid instanceof Payment ? ['amount' => (int) $paid->amount, 'channel' => $paid->channel, 'paid_at' => $paid->paid_at] : null,
        ];
    }
}
