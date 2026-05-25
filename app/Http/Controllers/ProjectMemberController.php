<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Support\ProjectAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectMemberController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        ProjectAccess::ensureCanManageTeam($request->user(), $project);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', Rule::in([
                ProjectAccess::ROLE_MEMBER,
                ProjectAccess::ROLE_MANAGER,
            ])],
        ]);

        abort_if(
            $project->members()->whereKey($validated['user_id'])->exists(),
            422,
            'Cet utilisateur est déjà membre du projet.',
        );

        $role = $validated['role'] ?? ProjectAccess::ROLE_MEMBER;

        $project->members()->attach($validated['user_id'], [
            'role' => $role,
            'joined_at' => now(),
        ]);

        return back();
    }

    public function update(Request $request, Project $project, User $user): RedirectResponse
    {
        ProjectAccess::ensureCanManageTeam($request->user(), $project);

        abort_unless($project->members()->whereKey($user->id)->exists(), 404);

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(array_keys(ProjectAccess::ROLES))],
        ]);

        if ((int) $project->owner_id === (int) $user->id && $validated['role'] !== ProjectAccess::ROLE_OWNER) {
            abort(422, 'Le propriétaire du projet ne peut pas changer de rôle.');
        }

        if ($validated['role'] === ProjectAccess::ROLE_OWNER && ! $request->user()->is_admin) {
            abort(403, 'Seul un administrateur peut nommer un propriétaire.');
        }

        $project->members()->updateExistingPivot($user->id, [
            'role' => $validated['role'],
        ]);

        if ($validated['role'] === ProjectAccess::ROLE_OWNER) {
            $project->update(['owner_id' => $user->id]);
        }

        return back();
    }

    public function destroy(Request $request, Project $project, User $user): RedirectResponse
    {
        ProjectAccess::ensureCanManageTeam($request->user(), $project);

        abort_unless($project->members()->whereKey($user->id)->exists(), 404);

        if (ProjectAccess::isProjectOwner($user, $project)) {
            abort(422, 'Le propriétaire du projet ne peut pas être retiré.');
        }

        $project->members()->detach($user->id);

        foreach ($project->ranks as $rank) {
            $rank->members()->detach($user->id);
            if ((int) $rank->responsible_id === (int) $user->id) {
                $rank->update(['responsible_id' => null]);
            }
        }

        return back();
    }
}
