<?php

namespace App\Http\Controllers;

use App\Events\DirectMessageSent;
use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\User;
use App\Support\DirectMessageAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                    ->with('user:id,name')
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
            ->with('user:id,name')
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

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
            'conversation_id' => ['nullable', 'integer', 'exists:direct_conversations,id'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $conversation = null;

        if (! empty($validated['conversation_id'])) {
            $conversation = DirectConversation::query()->findOrFail($validated['conversation_id']);
            DirectMessageAccess::ensureAccess($user, $conversation);
        } elseif (! empty($validated['recipient_id'])) {
            $recipient = User::query()->findOrFail($validated['recipient_id']);
            DirectMessageAccess::ensureCanMessage($user, $recipient);
            $conversation = DirectConversation::findOrCreateBetween($user->id, $recipient->id);
        } else {
            abort(422, 'Destinataire ou conversation requis.');
        }

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => trim($validated['body']),
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);

        $message->load('user:id,name');

        DirectMessageSent::dispatch($message);

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
