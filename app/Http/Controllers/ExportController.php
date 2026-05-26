<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Bug;
use App\Models\Project;
use App\Support\BugVisibility;
use App\Models\Task;
use App\Support\ProjectAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function myTasks(Request $request): StreamedResponse
    {
        $user = $request->user();

        $tasks = Task::query()
            ->where('assignee_id', $user->id)
            ->whereNull('archived_at')
            ->with(['project:id,name,slug', 'list:id,name'])
            ->orderBy('due_date')
            ->get();

        return $this->csv('mes-taches.csv', [
            'Projet', 'Liste', 'Titre', 'Priorité', 'Échéance', 'Statut', 'En retard',
        ], $tasks->map(fn (Task $t) => [
            $t->project?->name,
            $t->list?->name,
            $t->title,
            $t->priority,
            optional($t->due_date)?->format('Y-m-d'),
            $t->status,
            $t->isOverdue() ? 'Oui' : 'Non',
        ]));
    }

    public function audit(Request $request): StreamedResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $query = AuditLog::query()->with('user:id,name,email');

        if ($action = $request->string('action')->toString()) {
            $query->where('action', $action);
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($from = $request->date('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->date('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->orderByDesc('created_at')->limit(5000)->get();

        return $this->csv('audit.csv', [
            'Date', 'Utilisateur', 'Email', 'Action', 'Message', 'IP',
        ], $logs->map(fn (AuditLog $log) => [
            $log->created_at?->format('Y-m-d H:i'),
            $log->user?->name,
            $log->user?->email,
            $log->action,
            $log->message,
            $log->ip_address,
        ]));
    }

    public function bugs(Request $request, Project $project): StreamedResponse
    {
        abort_unless(
            $request->user()->is_admin || $project->members()->whereKey($request->user()->id)->exists(),
            403,
        );

        $user = $request->user();
        $bugs = BugVisibility::filterAccessible(
            $user,
            $project->bugs()
                ->with(['reporter:id,name', 'assignee:id,name'])
                ->orderByDesc('created_at')
                ->get(),
            $project,
        );

        return $this->csv("bugs-{$project->slug}.csv", [
            'Titre', 'Priorité', 'Statut', 'Rapporteur', 'Assigné', 'SLA', 'Créé le',
        ], $bugs->map(fn (Bug $b) => [
            $b->title,
            $b->priority,
            $b->status,
            $b->reporter?->name,
            $b->assignee?->name,
            optional($b->sla_due_at)?->format('Y-m-d H:i'),
            $b->created_at?->format('Y-m-d H:i'),
        ]));
    }

    public function projectActivity(Request $request, Project $project): StreamedResponse
    {
        ProjectAccess::ensureAccess($request->user(), $project);

        $logs = ActivityLog::query()
            ->where('project_id', $project->id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get();

        return $this->csv("activite-{$project->slug}.csv", [
            'Date', 'Utilisateur', 'Email', 'Action', 'Message',
        ], $logs->map(fn (ActivityLog $log) => [
            $log->created_at?->format('Y-m-d H:i'),
            $log->user?->name,
            $log->user?->email,
            $log->action,
            $log->message,
        ]));
    }

    private function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
