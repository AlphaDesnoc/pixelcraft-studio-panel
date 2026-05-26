<?php

namespace App\Http\Controllers;

use App\Support\NotificationPreferences;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'status' => session('status'),
            'two_factor_enabled' => (bool) $user->two_factor_confirmed_at,
            'notificationPreferences' => NotificationPreferences::forUser($user),
            'notificationTypes' => collect(NotificationPreferences::labels())
                ->map(fn (string $label, string $type) => ['type' => $type, 'label' => $label])
                ->values(),
        ]);
    }
}
