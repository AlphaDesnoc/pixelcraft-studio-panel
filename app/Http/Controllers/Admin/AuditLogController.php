<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'user' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = AuditLog::query()->with('user:id,name,email');

        if (! empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        if (! empty($validated['user'])) {
            $term = $validated['user'];
            $query->whereHas('user', function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        if (! empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        $logs = $query
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (AuditLog $log) => $log->toPayload());

        $actionOptions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values();

        return Inertia::render('Admin/Audit/Index', [
            'logs' => $logs,
            'filters' => $validated,
            'actionOptions' => $actionOptions,
        ]);
    }
}
