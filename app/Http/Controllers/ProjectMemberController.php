<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\Project;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\ProjectAccess;
use App\Support\ProjectPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectMemberController extends Controller
{
    use EnsuresProjectFeature;
    use RespondsForApi;

    public function store(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'team');
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

        $member = User::query()->findOrFail($validated['user_id']);

        AuditLogger::log(
            $request->user(),
            'project_member_added',
            sprintf(
                '%s a ajouté %s au projet « %s » (%s)',
                $request->user()->name,
                $member->name,
                $project->name,
                ProjectAccess::ROLES[$role] ?? $role,
            ),
            $project,
            [
                'user_id' => $member->id,
                'user_email' => $member->email,
                'role' => $role,
            ],
            $request,
        );

        return $this->apiOrBack($request, [
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $role,
            ],
        ]);
    }

    public function update(Request $request, Project $project, User $user): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'team');
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

        $previousRole = ProjectAccess::memberRole($user, $project);

        $project->members()->updateExistingPivot($user->id, [
            'role' => $validated['role'],
        ]);

        if ($validated['role'] === ProjectAccess::ROLE_OWNER) {
            $project->update(['owner_id' => $user->id]);
        }

        if ($previousRole !== $validated['role']) {
            AuditLogger::log(
                $request->user(),
                'project_member_updated',
                sprintf(
                    '%s a modifié le rôle de %s sur « %s » (%s → %s)',
                    $request->user()->name,
                    $user->name,
                    $project->name,
                    ProjectAccess::ROLES[$previousRole] ?? $previousRole,
                    ProjectAccess::ROLES[$validated['role']] ?? $validated['role'],
                ),
                $project,
                [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'from' => $previousRole,
                    'to' => $validated['role'],
                ],
                $request,
            );
        }

        return $this->apiOrBack($request, [
            'member' => [
                'id' => $user->id,
                'role' => $validated['role'],
            ],
        ]);
    }

    public function permissions(Request $request, Project $project, User $user): JsonResponse|RedirectResponse
    {
        $this->ensureFeature($request, $project, 'team');
        ProjectAccess::ensureCanManageTeam($request->user(), $project);
        abort_unless($project->members()->whereKey($user->id)->exists(), 404);

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['boolean'],
        ]);

        $project->members()->updateExistingPivot($user->id, [
            'permissions' => ProjectPermissions::sanitize($validated['permissions']),
        ]);

        return $this->apiOrBack($request, [
            'permissions' => ProjectPermissions::sanitize($validated['permissions']),
        ]);
    }

    public function destroy(Request $request, Project $project, User $user): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'team');
        ProjectAccess::ensureCanManageTeam($request->user(), $project);

        abort_unless($project->members()->whereKey($user->id)->exists(), 404);

        if (ProjectAccess::isProjectOwner($user, $project)) {
            abort(422, 'Le propriétaire du projet ne peut pas être retiré.');
        }

        $memberRole = ProjectAccess::memberRole($user, $project);

        $project->members()->detach($user->id);

        foreach ($project->ranks as $rank) {
            $rank->members()->detach($user->id);
            if ((int) $rank->responsible_id === (int) $user->id) {
                $rank->update(['responsible_id' => null]);
            }
        }

        AuditLogger::log(
            $request->user(),
            'project_member_removed',
            sprintf(
                '%s a retiré %s du projet « %s »',
                $request->user()->name,
                $user->name,
                $project->name,
            ),
            $project,
            [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'role' => $memberRole,
            ],
            $request,
        );

        return $this->apiOrBack($request, ['user_id' => $user->id]);
    }
}
