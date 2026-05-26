<?php

namespace App\Http\Controllers;

use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\UserPresence;
use App\Support\PanelNotifier;
use App\Support\PresenceUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    private const ONLINE_WINDOW_SECONDS = 90;

    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();

        UserPresence::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['last_seen_at' => now()],
        );

        return response()->json(['ok' => true]);
    }

    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();
        $since = $request->date('since');

        UserPresence::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['last_seen_at' => now()],
        );

        $unreadCount = (int) DirectConversation::query()
            ->where(function ($q) use ($user) {
                $q->where('user_one_id', $user->id)
                    ->orWhere('user_two_id', $user->id);
            })
            ->get()
            ->sum(fn (DirectConversation $c) => $c->unreadCountFor($user));

        $onlineUsers = UserPresence::query()
            ->where('last_seen_at', '>=', now()->subSeconds(self::ONLINE_WINDOW_SECONDS))
            ->with('user:id,name')
            ->get()
            ->map(fn (UserPresence $p) => PresenceUser::payload($p->user))
            ->values();

        $events = collect();
        if ($since) {
            $events = DirectMessage::query()
                ->where('created_at', '>', $since)
                ->where('user_id', '!=', $user->id)
                ->whereHas('conversation', function ($q) use ($user) {
                    $q->where('user_one_id', $user->id)
                        ->orWhere('user_two_id', $user->id);
                })
                ->with([
                    'user:id,name',
                    'conversation.userOne:id,name,email',
                    'conversation.userTwo:id,name,email',
                ])
                ->orderBy('created_at')
                ->get()
                ->map(function (DirectMessage $message) {
                    $conversation = $message->conversation;

                    return [
                        'message' => $message->toPayload(),
                        'inbox' => [
                            'id' => $conversation->id,
                            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                            'last_message' => [
                                'id' => $message->id,
                                'body' => $message->body,
                                'created_at' => $message->created_at?->toIso8601String(),
                                'user_id' => $message->user_id,
                            ],
                            'participants' => [
                                [
                                    'id' => $conversation->userOne->id,
                                    'name' => $conversation->userOne->name,
                                    'email' => $conversation->userOne->email,
                                ],
                                [
                                    'id' => $conversation->userTwo->id,
                                    'name' => $conversation->userTwo->name,
                                    'email' => $conversation->userTwo->email,
                                ],
                            ],
                        ],
                    ];
                })
                ->values();
        }

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'unread_count' => $unreadCount,
            'unread_notifications' => PanelNotifier::unreadCount($user),
            'online_users' => $onlineUsers,
            'events' => $events,
            'echo_available' => config('broadcasting.default') === 'reverb',
        ]);
    }
}
