<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $query = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        $unreadCount = (clone $query)->whereNull('read_at')->count();

        if ($request->header('X-Inertia')) {
            return Inertia::render('Notifications/Index', [
                'notifications' => $query
                    ->paginate(30)
                    ->withQueryString()
                    ->through(fn (UserNotification $n) => $n->toPayload()),
                'unread_count' => $unreadCount,
            ]);
        }

        if ($request->has('page')) {
            $paginated = $query->paginate(min(50, max(1, (int) $request->input('per_page', 30))));

            return response()->json([
                'notifications' => $paginated->through(fn (UserNotification $n) => $n->toPayload()),
                'unread_count' => $unreadCount,
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        $notifications = $query
            ->limit(30)
            ->get()
            ->map(fn (UserNotification $n) => $n->toPayload());

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true, 'unread_count' => 0]);
    }
}
