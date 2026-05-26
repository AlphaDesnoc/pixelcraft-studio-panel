<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileDashboardWidgetsController extends Controller
{
    public const WIDGETS = [
        'stats' => 'Statistiques',
        'projects' => 'Mes projets',
    ];

    /** @return array<string, bool> */
    public static function defaults(): array
    {
        return array_fill_keys(array_keys(self::WIDGETS), true);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'widgets' => ['required', 'array'],
            'widgets.*' => ['boolean'],
        ]);

        $merged = self::defaults();
        foreach (array_keys(self::WIDGETS) as $key) {
            if (array_key_exists($key, $validated['widgets'])) {
                $merged[$key] = (bool) $validated['widgets'][$key];
            }
        }

        $request->user()->update(['dashboard_widgets' => $merged]);

        return back();
    }
}
