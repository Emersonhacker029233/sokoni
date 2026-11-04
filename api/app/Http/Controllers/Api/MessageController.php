<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\Moderation\BlockList;
use App\Services\Push\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function __construct(
        private readonly PushNotifier $push,
        private readonly BlockList $blocks,
    ) {}

    /**
     * Paginated messages, oldest first. Also marks every message from the
     * other party as read — the app polls this endpoint every 5s while a
     * thread is open (CLAUDE.md feature 5: no WebSocket server on shared
     * hosting), which doubles as the read-receipt mechanism.
     */
    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('view', $conversation);

        $other = $conversation->otherParty($request->user());
        if ($this->blocks->hasBlocked($request->user(), $other?->id)) {
            return BlockList::forbidden('You blocked this account.', $other?->id);
        }

        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // A message a moderator hid is gone for both people in the thread.
        $messages = $conversation->messages()->where('is_hidden', false)->with('sender')->paginate(30);

        return MessageResource::collection($messages);
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): MessageResource|JsonResponse
    {
        // Blocked either way: no new messages between the two (Apple
        // Guideline 1.2). Same answer for both sides, so the blocked
        // person can't tell who blocked whom.
        $other = $conversation->otherParty($request->user());
        if ($other !== null && $this->blocks->eitherBlocked($request->user(), $other)) {
            return BlockList::forbidden("You can't send messages to this account.");
        }

        // Stored as a full URL, not a disk-relative path — matches the
        // convention every other media field in the API already uses
        // (see ProductMediaController), so the client never needs to know
        // about the storage disk to render an attachment.
        $attachmentUrl = $request->hasFile('attachment')
            ? Storage::disk('public')->url($request->file('attachment')->store('chat', 'public'))
            : null;

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $request->string('body') ?: null,
            'attachment' => $attachmentUrl,
        ]);

        $conversation->update(['last_message_at' => now()]);

        $recipient = $request->user()->id === $conversation->buyer_id
            ? $conversation->seller->user
            : $conversation->buyer;

        // Part 4 (client feedback): Settings' Notifications section — "messages" toggle.
        if ($recipient->notify_messages) {
            $this->push->notify(
                $recipient,
                'New message',
                $message->body ?? 'Sent an image',
                ['conversation_id' => $conversation->id],
                actor: $request->user(),
            );
        }

        return new MessageResource($message->load('sender'));
    }
}
