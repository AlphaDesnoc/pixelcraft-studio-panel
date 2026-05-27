<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            ->map(fn (User $user) => $this->userPayload($user));

        return response()->json([
            'users' => $users,
            'roles' => User::ROLES,
            'emailDomain' => config('pixelcraft.email_domain'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $domain = config('pixelcraft.email_domain');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pseudo' => [
                'required',
                'string',
                'min:2',
                'max:60',
                'regex:/^[a-z0-9._-]+$/i',
            ],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
        ], [
            'pseudo.regex' => 'Le pseudo ne peut contenir que des lettres, chiffres, points, tirets et underscores.',
        ]);

        $email = strtolower($validated['pseudo']).'@'.$domain;

        $request->validate([
            'pseudo' => Rule::unique('users', 'email')->where(fn ($q) => $q->where('email', $email)),
        ], [
            'pseudo.unique' => 'Ce pseudo est déjà utilisé.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->loadCount([
            'projects as projects_count',
            'assignedTasks as tasks_count',
        ]);

        return response()->json([
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $domain = config('pixelcraft.email_domain');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pseudo' => [
                'required',
                'string',
                'min:2',
                'max:60',
                'regex:/^[a-z0-9._-]+$/i',
            ],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['nullable', Password::defaults()],
        ], [
            'pseudo.regex' => 'Le pseudo ne peut contenir que des lettres, chiffres, points, tirets et underscores.',
        ]);

        $email = strtolower($validated['pseudo']).'@'.$domain;

        $request->validate([
            'pseudo' => Rule::unique('users', 'email')
                ->where(fn ($q) => $q->where('email', $email))
                ->ignore($user->id),
        ], [
            'pseudo.unique' => 'Ce pseudo est déjà utilisé.',
        ]);

        $isSelf = $user->id === $request->user()->id;

        if ($isSelf && $validated['role'] !== User::ROLE_ADMIN) {
            return response()->json([
                'message' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.',
            ], 422);
        }

        $previousRole = $user->role;

        $user->name = $validated['name'];
        $user->email = $email;
        $user->role = $validated['role'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if ($previousRole !== $user->role) {
            AuditLogger::log(
                $request->user(),
                'user_role_changed',
                sprintf(
                    '%s a changé le rôle de %s (%s → %s)',
                    $request->user()->name,
                    $user->name,
                    $previousRole,
                    $user->role,
                ),
                $user,
                ['from' => $previousRole, 'to' => $user->role, 'email' => $user->email],
                $request,
            );
        }

        $user->loadCount([
            'projects as projects_count',
            'assignedTasks as tasks_count',
        ]);

        return response()->json([
            'user' => $this->userPayload($user),
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

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 422);
        }

        $name = $user->name;
        $email = $user->email;
        $user->delete();

        AuditLogger::log(
            $request->user(),
            'user_deleted',
            sprintf('%s a supprimé le compte de %s', $request->user()->name, $name),
            null,
            ['email' => $email, 'name' => $name],
            $request,
        );

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'pseudo' => $user->pseudo,
            'role' => $user->role,
            'is_admin' => $user->is_admin,
            'is_active' => $user->is_active,
            'projects_count' => $user->projects_count ?? 0,
            'tasks_count' => $user->tasks_count ?? 0,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
