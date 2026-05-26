<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
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

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => User::ROLES,
            'emailDomain' => config('pixelcraft.email_domain'),
        ]);
    }

    public function store(Request $request): RedirectResponse
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

        User::create([
            'name' => $validated['name'],
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'Utilisateur créé.');
    }

    public function update(Request $request, User $user): RedirectResponse
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
            return back()->withErrors([
                'role' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.',
            ]);
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

        return back()->with('success', 'Utilisateur mis à jour.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors([
                'user' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ]);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user->is_active = $validated['is_active'];
        $user->save();

        AuditLogger::log(
            $request->user(),
            $validated['is_active'] ? 'user_activated' : 'user_deactivated',
            sprintf(
                '%s a %s le compte de %s',
                $request->user()->name,
                $validated['is_active'] ? 'réactivé' : 'désactivé',
                $user->name,
            ),
            $user,
            ['email' => $user->email],
            $request,
        );

        if (! $user->is_active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with(
            'success',
            $user->is_active ? 'Utilisateur réactivé.' : 'Utilisateur désactivé.'
        );
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors([
                'user' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ]);
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

        return back()->with('success', 'Utilisateur supprimé.');
    }
}
