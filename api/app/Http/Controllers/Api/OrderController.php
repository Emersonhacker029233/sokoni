<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Push\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /** Flat delivery fee in TZS when delivery_method is 'delivery'. No fee schedule is specified yet — see DECISIONS.md. */
    private const DELIVERY_FEE = 3000;

    public function __construct(private readonly PushNotifier $push) {}

    /** The signed-in buyer's own orders. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->with(['seller', 'items', 'review'])
            ->latest()
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    /** Orders placed with the signed-in user's shop. */
    public function shopOrders(Request $request): AnonymousResourceCollection
    {
        $seller = $request->user()->sellerProfile()->first();
        abort_unless($seller, 403, 'Not a seller.');

        $orders = $seller->orders()
            ->with(['buyer', 'items', 'review'])
            ->latest()
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        $this->authorize('view', $order);
        $order->load(['buyer', 'seller', 'items', 'review']);

        return new OrderResource($order);
    }

    public function store(CheckoutRequest $request): OrderResource
    {
        $products = Product::query()
            ->visible()
            ->whereIn('id', collect($request->input('items'))->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $order = DB::transaction(function () use ($request, $products) {
            $sellerId = $products->first()->seller_id;
            $subtotal = 0;

            foreach ($request->input('items') as $line) {
                $product = $products->get($line['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages(['items' => 'One or more products are unavailable.']);
                }
                $subtotal += $product->price * $line['qty'];
            }

            $deliveryFee = $request->input('delivery_method') === 'delivery' ? self::DELIVERY_FEE : 0;

            $order = $request->user()->orders()->create([
                'seller_id' => $sellerId,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total' => $subtotal + $deliveryFee,
                'delivery_method' => $request->string('delivery_method'),
                'address' => $request->string('address') ?: null,
                'delivery_lat' => $request->float('delivery_lat') ?: null,
                'delivery_lng' => $request->float('delivery_lng') ?: null,
                'notes' => $request->string('notes') ?: null,
                'payment_method' => $request->string('payment_method'),
            ]);

            foreach ($request->input('items') as $line) {
                $product = $products->get($line['product_id']);
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'title_snapshot' => $product->title,
                    'price_snapshot' => $product->price,
                    'qty' => $line['qty'],
                ]);
            }

            // The order thread opens automatically in chat (CLAUDE.md feature 8).
            Conversation::query()->firstOrCreate(
                ['buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id, 'order_id' => $order->id],
                ['last_message_at' => now()]
            );

            return $order;
        });

        $this->push->notify(
            $order->seller->user,
            'New order '.$order->code,
            'You have a new order worth TSh '.number_format($order->total).'.',
            ['order_id' => $order->id],
        );

        $order->load(['buyer', 'seller', 'items']);

        return new OrderResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        $status = $request->string('status')->toString();

        if ($status === 'cancelled') {
            $this->authorize('cancel', $order);
        } else {
            $this->authorize('advance', $order);
        }

        if (! $order->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move an order from '{$order->status}' to '{$status}'.",
            ]);
        }

        // forceFill: status/timestamp columns are deliberately excluded from
        // Order's #[Fillable] (they must only change via this validated
        // transition, never via a raw mass-assignable update elsewhere).
        $order->forceFill([
            'status' => $status,
            "{$status}_at" => now(),
            ...($status === 'cancelled' ? ['cancelled_reason' => $request->string('reason')] : []),
            ...($status === 'completed' ? ['payment_status' => 'paid'] : []),
        ])->save();

        $notifyUser = $request->user()->id === $order->buyer_id ? $order->seller->user : $order->buyer;
        $this->push->notify(
            $notifyUser,
            'Order '.$order->code.' updated',
            "Status is now: {$status}.",
            ['order_id' => $order->id],
        );

        return new OrderResource($order->fresh(['buyer', 'seller', 'items']));
    }
}
