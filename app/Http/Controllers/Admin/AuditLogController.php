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
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (AuditLog $log) => $log->toPayload());

        return Inertia::render('Admin/Audit/Index', [
            'logs' => $logs,
        ]);
    }
}
