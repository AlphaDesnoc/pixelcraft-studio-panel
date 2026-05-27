<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileThemeController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme_preference' => ['required', 'string', 'in:light,dark,system'],
        ]);

        $user = $request->user();
        $user->update([
            'theme_preference' => $validated['theme_preference'],
        ]);

        return response()->json([
            'theme_preference' => $user->theme_preference,
        ]);
    }
}
