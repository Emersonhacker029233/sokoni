<?php

namespace Tests\Feature\Returns;

use App\Models\DeliveryJob;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\SellerProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Delivery\DeliveryTestHelpers;

/**
 * A Bolt-delivered order from a shop with a return policy, and the
 * buyer's return request, through the real endpoints. Bolt is the
 * FakesBolt sandbox (see DeliveryTestHelpers).
 */
trait ReturnsTestHelpers
{
    use DeliveryTestHelpers;

    protected function setUpReturns(): void
    {
        $this->setUpDelivery();
        Storage::fake('local');
        config(['delivery.returns_enabled' => true, 'payments.provider' => 'fake', 'payments.fake.approve_after_seconds' => 8]);
    }

    /** Placed at checkout with $policy, delivered by Bolt just now. */
    protected function deliveredOrder(string $policy = '3d', ?SellerProfile $shop = null): Order
    {
        $shop ??= $this->shop(['return_policy' => $policy, 'return_notes' => 'Unused, in the original box.']);
        $job = $this->bookedJob(shop: $shop);
        $this->boltReceipt = ['amount' => 6000, 'currency_code' => 'TZS', 'fare_breakdown' => []];
        $job = $this->boltMoves($job, 'COMPLETED');
        $this->assertSame('delivered', $job->status);
        $this->boltReceipt = null;

        return $job->order->fresh();
    }

    protected function requestReturn(Order $order, string $reason = 'wrong_item', int $photos = 2, ?string $comment = 'It is the blue one, I ordered black.'): TestResponse
    {
        return $this->actingAs($order->buyer)->post("/api/orders/{$order->id}/returns", [
            'reason' => $reason,
            'comment' => $comment,
            'photos' => $photos > 0 ? array_map(fn ($i) => UploadedFile::fake()->image("photo{$i}.jpg", 800, 600), range(1, $photos)) : [],
        ], ['Accept' => 'application/json']);
    }

    protected function requestedReturn(string $reason = 'wrong_item', string $policy = '3d'): ReturnRequest
    {
        $order = $this->deliveredOrder($policy);
        $id = $this->requestReturn($order, $reason)->assertCreated()->json('data.id');

        return ReturnRequest::query()->findOrFail($id);
    }

    protected function asSeller(ReturnRequest $return): static
    {
        return $this->actingAs($return->shop->user->fresh());
    }

    protected function asBuyer(ReturnRequest $return): static
    {
        return $this->actingAs($return->buyer->fresh());
    }

    protected function deliveryJobOf(ReturnRequest $return): DeliveryJob
    {
        return $return->order->deliveryJob;
    }
}
