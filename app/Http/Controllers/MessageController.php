<?php

namespace App\Http\Controllers;

use App\Events\DirectMessageSent;
use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\DirectMessageAccess;
use App\Support\MentionParser;
use App\Support\PanelNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $selectedId = $request->integer('c') ?: null;

        $conversations = DirectConversation::query()
            ->where(function ($q) use ($user) {
                $q->where('user_one_id', $user->id)
                    ->orWhere('user_two_id', $user->id);
            })
            ->with([
                'userOne:id,name,email',
                'userTwo:id,name,email',
                'messages' => fn ($q) => $q->latest()->limit(1)->with('user:id,name'),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (DirectConversation $conv) => $this->serializeConversation($conv, $user))
            ->values();

        $contacts = DirectMessageAccess::sharedContacts($user)
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])
            ->values();

        $selectedConversation = null;
        $messages = collect();

        if ($selectedId) {
            $conv = DirectConversation::query()->find($selectedId);
            if ($conv && DirectMessageAccess::canAccess($user, $conv)) {
                $selectedConversation = $this->serializeConversation(
                    $conv->load(['userOne:id,name,email', 'userTwo:id,name,email']),
                    $user,
                );
                $messages = $conv->messages()
                    ->with(['user:id,name', 'attachments', 'replyTo.user:id,name'])
                    ->orderBy('created_at')
                    ->get()
                    ->map(fn (DirectMessage $m) => $m->toPayload())
                    ->values();

                $conv->markReadFor($user);
            }
        }

        return Inertia::render('Messages/Index', [
            'conversations' => $conversations,
            'contacts' => $contacts,
            'selectedConversationId' => $selectedConversation['id'] ?? null,
            'selectedConversation' => $selectedConversation,
            'messages' => $messages,
        ]);
    }

    public function messages(Request $request, DirectConversation $conversation): JsonResponse
    {
        $user = $request->user();
        DirectMessageAccess::ensureAccess($user, $conversation);

        $messages = $conversation->messages()
            ->with(['user:id,name', 'attachments', 'replyTo.user:id,name'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (DirectMessage $m) => $m->toPayload())
            ->values();

        $conversation->markReadFor($user);

        return response()->json(['messages' => $messages]);
    }

    public function markRead(Request $request, DirectConversation $conversation): JsonResponse
    {
        DirectMessageAccess::ensureAccess($request->user(), $conversation);
        $conversation->markReadFor($request->user());

        return response()->json(['ok' => true]);
    }

    public function storeAttachment(Request $request, DirectConversation $conversation): JsonResponse
    {
        $user = $request->user();
        DirectMessageAccess::ensureAccess($user, $conversation);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'reply_to_id' => ['nullable', 'integer', 'exists:direct_messages,id'],
        ]);

        $file = $validated['file'];
        $path = $file->store("direct/{$conversation->id}", 'public');
        $mimeType = $file->getMimeType() ?: $file->getClientMimeType();

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => '',
            'reply_to_id' => $validated['reply_to_id'] ?? null,
        ]);

        $attachment = $message->attachments()->create([
            'user_id' => $user->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);
        $message->load(['user:id,name', 'attachments', 'replyTo.user:id,name']);

        DirectMessageSent::dispatch($message);

        $recipient = $conversation->otherParticipant($user);
        if ($recipient && $recipient->id !== $user->id) {
            PanelNotifier::send(
                $recipient,
                UserNotification::TYPE_DIRECT_MESSAGE,
                'Nouveau message privé',
                sprintf('%s a envoyé un fichier : %s', $user->name, $attachment->original_name),
                route('messages.index', ['c' => $conversation->id]),
                ['conversation_id' => $conversation->id],
            );
        }

        return response()->json([
            'message' => $message->toPayload(),
            'conversation' => $this->serializeConversation(
                $conversation->load(['userOne:id,name,email', 'userTwo:id,name,email', 'messages' => fn ($q) => $q->latest()->limit(1)->with('user:id,name')]),
                $user,
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
            'conversation_id' => ['nullable', 'integer', 'exists:direct_conversations,id'],
            'body' => ['required', 'string', 'max:5000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:direct_messages,id'],
        ]);

        $conversation = null;
        $candidates = collect();

        if (! empty($validated['conversation_id'])) {
            $conversation = DirectConversation::query()->findOrFail($validated['conversation_id']);
            DirectMessageAccess::ensureAccess($user, $conversation);
            $other = $conversation->otherParticipant($user);
            if ($other) {
                $candidates = collect([$other]);
            }
        } elseif (! empty($validated['recipient_id'])) {
            $recipient = User::query()->findOrFail($validated['recipient_id']);
            DirectMessageAccess::ensureCanMessage($user, $recipient);
            $conversation = DirectConversation::findOrCreateBetween($user->id, $recipient->id);
            $candidates = collect([$recipient]);
        } else {
            abort(422, 'Destinataire ou conversation requis.');
        }

        $body = trim($validated['body']);
        $mentions = MentionParser::extract($body, $candidates);

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => $body,
            'mentions' => $mentions,
            'reply_to_id' => $validated['reply_to_id'] ?? null,
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);

        $message->load(['user:id,name', 'attachments', 'replyTo.user:id,name']);

        DirectMessageSent::dispatch($message);

        $recipient = $conversation->otherParticipant($user);
        if ($recipient && $recipient->id !== $user->id) {
            $mentionedIds = collect($mentions)->pluck('id');
            if ($mentionedIds->contains($recipient->id)) {
                PanelNotifier::send(
                    $recipient,
                    UserNotification::TYPE_CHAT_MENTION,
                    'Mention en message privé',
                    sprintf('%s vous a mentionné : %s', $user->name, str($body)->limit(80)),
                    route('messages.index', ['c' => $conversation->id]),
                    ['conversation_id' => $conversation->id],
                );
            } else {
                PanelNotifier::send(
                    $recipient,
                    UserNotification::TYPE_DIRECT_MESSAGE,
                    'Nouveau message privé',
                    sprintf('%s : %s', $user->name, str($body)->limit(80)),
                    route('messages.index', ['c' => $conversation->id]),
                    ['conversation_id' => $conversation->id],
                );
            }
        }

        return response()->json([
            'message' => $message->toPayload(),
            'conversation' => $this->serializeConversation(
                $conversation->load(['userOne:id,name,email', 'userTwo:id,name,email', 'messages' => fn ($q) => $q->latest()->limit(1)->with('user:id,name')]),
                $user,
            ),
        ]);
    }

    private function serializeConversation(DirectConversation $conv, User $viewer): array
    {
        $other = $conv->otherParticipant($viewer);
        $latest = $conv->messages->first();

        return [
            'id' => $conv->id,
            'last_message_at' => optional($conv->last_message_at)?->toIso8601String(),
            'unread_count' => $conv->unreadCountFor($viewer),
            'participant' => $other ? [
                'id' => $other->id,
                'name' => $other->name,
                'email' => $other->email,
            ] : null,
            'last_message' => $latest ? [
                'id' => $latest->id,
                'body' => $latest->body,
                'created_at' => $latest->created_at?->toIso8601String(),
                'user_id' => $latest->user_id,
            ] : null,
        ];
    }
}
