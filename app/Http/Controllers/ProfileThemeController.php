<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileThemeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme_preference' => ['required', 'string', 'in:light,dark,system'],
        ]);

        $request->user()->update([
            'theme_preference' => $validated['theme_preference'],
        ]);

        return back();
    }
}
