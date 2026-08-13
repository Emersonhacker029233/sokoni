<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\Push\PushNotifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function __construct(private readonly PushNotifier $push) {}

    /**
     * Paginated messages, oldest first. Also marks every message from the
     * other party as read — the app polls this endpoint every 5s while a
     * thread is open (CLAUDE.md feature 5: no WebSocket server on shared
     * hosting), which doubles as the read-receipt mechanism.
     */
    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        $this->authorize('view', $conversation);

        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $conversation->messages()->with('sender')->paginate(30);

        return MessageResource::collection($messages);
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): MessageResource
    {
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

        $this->push->notify(
            $recipient,
            'New message',
            $message->body ?? 'Sent an image',
            ['conversation_id' => $conversation->id],
        );

        return new MessageResource($message->load('sender'));
    }
}
