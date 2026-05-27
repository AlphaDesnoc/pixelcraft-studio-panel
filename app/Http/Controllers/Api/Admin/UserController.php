<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->withCount([
                'projects as projects_count',
                'assignedTasks as tasks_count',
            ])
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'email',
                'role',
                'is_active',
                'created_at',
            ])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'pseudo' => $user->pseudo,
                'role' => $user->role,
                'is_admin' => $user->is_admin,
                'is_active' => $user->is_active,
                'projects_count' => $user->projects_count,
                'tasks_count' => $user->tasks_count,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'users' => $users,
            'roles' => User::ROLES,
            'emailDomain' => config('pixelcraft.email_domain'),
        ]);
    }

    public function toggleActive(User $user): JsonResponse
    {
        if ($user->is_admin) {
            abort(422, 'Impossible de désactiver un administrateur.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'is_active' => $user->is_active,
            ],
        ]);
    }
}
