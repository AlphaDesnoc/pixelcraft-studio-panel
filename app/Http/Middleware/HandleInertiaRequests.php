<?php

namespace App\Http\Middleware;

use App\Models\DirectConversation;
use App\Support\PanelNotifier;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'sidebar' => [
                'projects' => fn () => $user
                    ? $user->projects()
                        ->orderBy('name')
                        ->get(['projects.id', 'projects.name', 'projects.slug', 'projects.image'])
                        ->map(fn ($p) => [
                            'id' => $p->id,
                            'name' => $p->name,
                            'slug' => $p->slug,
                            'image_url' => $p->image_url,
                        ])
                    : [],
                'unread_messages' => fn () => $user
                    ? (int) DirectConversation::query()
                        ->where(function ($q) use ($user) {
                            $q->where('user_one_id', $user->id)
                                ->orWhere('user_two_id', $user->id);
                        })
                        ->get()
                        ->sum(fn (DirectConversation $c) => $c->unreadCountFor($user))
                    : 0,
                'unread_notifications' => fn () => $user
                    ? PanelNotifier::unreadCount($user)
                    : 0,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'desktop' => [
                'isDesktop' => $request->header('X-PixelCraft-Desktop') === '1'
                    || str_contains($request->userAgent() ?? '', 'PixelCraftPanel'),
                'downloadUrl' => config('pixelcraft.desktop_download_url'),
            ],
        ];
    }
}
