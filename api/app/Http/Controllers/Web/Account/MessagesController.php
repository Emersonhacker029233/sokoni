<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Services\Push\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The account area's message centre — same `Conversation`/`Message`
 * models and the same 5s-polling design the app uses (CLAUDE.md feature
 * 5: no WebSocket server on shared hosting), just over a session-
 * authenticated JSON `poll()` endpoint instead of the Sanctum-token API,
 * since a browser session here has no bearer token to call the API with.
 */
class MessagesController extends Controller
{
    public function __construct(private readonly PushNotifier $push) {}

    public function index(): View
    {
        $user = Auth::user();
        $sellerId = $user->sellerProfileId();

        $conversations = Conversation::query()
            ->where(function ($q) use ($user, $sellerId) {
                $q->where('buyer_id', $user->id);
                if ($sellerId) {
                    $q->orWhere('seller_id', $sellerId);
                }
            })
            ->with(['buyer', 'seller', 'product', 'messages'])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return view('web.account.messages', ['conversations' => $conversations, 'title' => __('site.account_messages')]);
    }

    public function show(Conversation $conversation): View
    {
        $this->authorize('view', $conversation);

        $this->markRead($conversation);
        $conversation->load(['buyer', 'seller', 'product', 'messages.sender']);

        return view('web.account.message-thread', ['conversation' => $conversation, 'title' => __('site.account_messages')]);
    }

    public function poll(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $this->markRead($conversation);

        $messages = $conversation->messages()->with('sender')->get();

        return response()->json([
            'messages' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'attachment' => $m->attachment,
                'mine' => $m->sender_id === Auth::id(),
                'sender_name' => $m->sender->name,
                'created_at' => $m->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): \Illuminate\Http\RedirectResponse
    {
        $attachmentUrl = $request->hasFile('attachment')
            ? Storage::disk('public')->url($request->file('attachment')->store('chat', 'public'))
            : null;

        $message = $conversation->messages()->create([
            'sender_id' => Auth::id(),
            'body' => $request->string('body') ?: null,
            'attachment' => $attachmentUrl,
        ]);

        $conversation->update(['last_message_at' => now()]);

        $recipient = Auth::id() === $conversation->buyer_id ? $conversation->seller->user : $conversation->buyer;
        $this->push->notify($recipient, 'New message', $message->body ?? 'Sent an image', ['conversation_id' => $conversation->id]);

        return redirect()->route('web.account.messages.show', $conversation);
    }

    private function markRead(Conversation $conversation): void
    {
        $conversation->messages()
            ->where('sender_id', '!=', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
