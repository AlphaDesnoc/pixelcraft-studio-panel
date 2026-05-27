<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RespondsForApi
{
    protected function apiOrBack(Request $request, mixed $payload = null): JsonResponse|RedirectResponse
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json($payload ?? ['ok' => true]);
        }

        return back();
    }
}
