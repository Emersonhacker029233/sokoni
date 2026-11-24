<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\PayDeliveryRequest;
use App\Http\Requests\Returns\StoreReturnRequest;
use App\Http\Resources\Returns\ReturnRequestResource;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\SellerProfile;
use App\Services\Delivery\DeliveryPaymentService;
use App\Services\Returns\ReturnActionException;
use App\Services\Returns\ReturnService;
use App\Support\DeliveryAccess;
use App\Support\Legal;
use App\Support\ReturnPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Returns (docs/delivery/RETURNS.md). Behind `delivery.enabled:returns`,
 * `auth:sanctum` and `delivery.access:returns` (routes/api_returns.php):
 * 404 for everyone outside the launch.
 */
class ReturnController extends Controller
{
    public function __construct(private readonly ReturnService $returns) {}

    /** GET /returns: the signed-in user's returns, as buyer and as shop. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $shopId = $user->sellerProfileId();

        $page = ReturnRequest::query()
            ->with(['order', 'photos', 'payments'])
            ->where(fn ($q) => $q->where('buyer_id', $user->id)->when($shopId, fn ($q) => $q->orWhere('shop_id', $shopId)))
            ->latest('id')
            ->paginate(20);

        return ReturnRequestResource::collection($page->through(
            fn (ReturnRequest $r) => ReturnRequestResource::for($r, $r->buyer_id === $user->id ? ReturnRequestResource::BUYER : ReturnRequestResource::SELLER)
        ));
    }

    public function show(Request $request, ReturnRequest $return, DeliveryPaymentService $payments): JsonResponse
    {
        $viewer = $this->viewer($request, $return);
        if ($viewer === ReturnRequestResource::BUYER) {
            $payments->refreshForReturn($return);
        }
        if ($return->isRideActive()) {
            $return = $this->returns->syncRide($return);
        }

        return $this->respond($request, $return->fresh(), $viewer);
    }

    /** POST /orders/{order}/returns */
    public function store(StoreReturnRequest $request, Order $order): JsonResponse
    {
        if (! DeliveryAccess::returnsEligible($request->user(), $order->seller()->with('user')->first())) {
            abort(404);
        }

        $return = $this->returns->create(
            $order, $request->user(), $request->string('reason')->toString(),
            $request->input('comment'), $request->file('photos', []),
        );

        return $this->respond($request, $return, ReturnRequestResource::BUYER)->setStatusCode(201);
    }

    public function accept(Request $request, ReturnRequest $return): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->sellerOnly($request, $return);

        return $this->respond($request, $this->returns->accept($return, $request->user(), 'seller', $data['note'] ?? null), ReturnRequestResource::SELLER);
    }

    public function reject(Request $request, ReturnRequest $return): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->sellerOnly($request, $return);

        return $this->respond($request, $this->returns->reject($return, $request->user(), $data['reason']), ReturnRequestResource::SELLER);
    }

    public function dispute(Request $request, ReturnRequest $return): JsonResponse
    {
        $data = $request->validate(['comment' => ['required', 'string', 'min:3', 'max:1000']]);
        $this->buyerOnly($request, $return);

        return $this->respond($request, $this->returns->dispute($return, $request->user(), $data['comment']), ReturnRequestResource::BUYER);
    }

    public function withdraw(Request $request, ReturnRequest $return): JsonResponse
    {
        $this->buyerOnly($request, $return);

        return $this->respond($request, $this->returns->withdraw($return, $request->user()), ReturnRequestResource::BUYER);
    }

    /** "Changed my mind": the buyer pays the Bolt ride back through Pay now. */
    public function pay(PayDeliveryRequest $request, ReturnRequest $return, DeliveryPaymentService $payments): JsonResponse
    {
        $this->buyerOnly($request, $return);
        $payments->startForReturn($return, $request->user(), $request->filled('channel') ? (string) $request->input('channel') : null, $request->string('msisdn'), $request->string('idempotency_key'));

        return $this->respond($request, $return->fresh(), ReturnRequestResource::BUYER)->setStatusCode(202);
    }

    /** The buyer is ready with the parcel: book Bolt to collect it. */
    public function book(Request $request, ReturnRequest $return): JsonResponse
    {
        $this->buyerOnly($request, $return);

        return $this->respond($request, $this->returns->bookRide($return, $request->user()), ReturnRequestResource::BUYER);
    }

    /** The shop: "item received, refund done". */
    public function refunded(Request $request, ReturnRequest $return): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->sellerOnly($request, $return);

        return $this->respond($request, $this->returns->confirmRefunded($return, $request->user(), $data['note'] ?? null), ReturnRequestResource::SELLER);
    }

    /** PUT /shop/return-policy: none | 24h | 3d | 7d, plus optional notes. */
    public function updatePolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'return_policy' => ['required', Rule::in(array_keys(ReturnPolicy::OPTIONS))],
            'return_notes' => ['nullable', 'string', 'max:500'],
        ]);
        $shop = SellerProfile::query()->where('user_id', $request->user()->id)->first();
        abort_if($shop === null, 404);

        $shop->forceFill(['return_policy' => $data['return_policy'], 'return_notes' => $data['return_notes'] ?? null])->save();

        return response()->json(['data' => ReturnPolicy::toArray($shop->return_policy, $shop->return_notes)]);
    }

    /**
     * POST /returns/terms/accept: agreement to the Return Policy section of
     * the Terms (1.2). Stored apart from terms_version, which iOS 1.0 (9)
     * compares with its own "1.1".
     */
    public function acceptTerms(Request $request): JsonResponse
    {
        $request->validate(['version' => ['required', 'string', Rule::in([Legal::RETURNS_TERMS_VERSION])]]);
        $request->user()->forceFill([
            'returns_terms_version' => Legal::RETURNS_TERMS_VERSION,
            'returns_terms_accepted_at' => now(),
        ])->save();

        return response()->json(['data' => ['returns_terms_version' => Legal::RETURNS_TERMS_VERSION, 'returns_terms_accepted' => true]]);
    }

    private function viewer(Request $request, ReturnRequest $return): string
    {
        $user = $request->user();
        if ($this->returns->isBuyer($user, $return)) {
            return ReturnRequestResource::BUYER;
        }
        if ($this->returns->isSeller($user, $return)) {
            return ReturnRequestResource::SELLER;
        }
        abort(404);
    }

    /** The buyer acts; the shop gets a clear 403; anyone else can't tell the return exists. */
    private function buyerOnly(Request $request, ReturnRequest $return): void
    {
        if ($this->returns->isBuyer($request->user(), $return)) {
            return;
        }
        abort_unless($this->returns->isSeller($request->user(), $return), 404);
        throw ReturnActionException::make('not_your_return', 403);
    }

    private function sellerOnly(Request $request, ReturnRequest $return): void
    {
        if ($this->returns->isSeller($request->user(), $return)) {
            return;
        }
        abort_unless($this->returns->isBuyer($request->user(), $return), 404);
        throw ReturnActionException::make('seller_must_decide', 403);
    }

    private function respond(Request $request, ReturnRequest $return, string $viewer): JsonResponse
    {
        return response()->json(['data' => ReturnRequestResource::for($return, $viewer)->resolve($request)]);
    }
}
