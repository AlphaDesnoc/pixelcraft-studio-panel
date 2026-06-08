<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\Project;
use App\Support\ActivityLogger;
use App\Models\Rank;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RankController extends Controller
{
    use RespondsForApi;

    public function index(Request $request, Project $project): Response|JsonResponse
    {
        $payload = $this->buildIndexPayload($request, $project);

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Projects/Ranks', $payload);
    }

    /** @return array<string, mixed> */
    public function buildIndexPayload(Request $request, Project $project): array
    {
        $user = $request->user();
        $isAdmin = $user->is_admin;
        $isMember = $project->members()->whereKey($user->id)->exists();
        abort_unless($isAdmin || $isMember, 403);

        $this->ensureDefaultRanks($project);

        $project->load([
            'members:id,name,email,avatar_path',
            'ranks' => fn ($q) => $q->orderBy('position'),
            'ranks.members:id,name,email,avatar_path',
        ]);

        $members = $project->members->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'avatar_url' => $m->avatar_url,
        ])->values();

        $ranks = $project->ranks
            ->map(fn ($rank) => $this->serializeRank($rank, $user))
            ->values();

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'image_url' => $project->image_url,
                'status' => $project->status,
            ],
            'ranks' => $ranks,
            'members' => $members,
            'canEdit' => $isAdmin,
        ];
    }

    public function store(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->ensureCanEdit($request, $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $maxPos = (int) $project->ranks()->max('position');

        $rank = $project->ranks()->create([
            'name' => $validated['name'],
            'slug' => Rank::uniqueSlug($project->id, $validated['name']),
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#7c5cff',
            'position' => $maxPos + 1,
        ]);

        return $this->apiOrBack($request, [
            'rank' => $this->serializeRank($rank->fresh(['members:id,name,email,avatar_path']), $request->user()),
        ]);
    }

    public function update(Request $request, Project $project, Rank $rank): JsonResponse|RedirectResponse
    {
        $this->ensureCanEdit($request, $project);
        abort_unless($rank->project_id === $project->id, 404);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'manages_bugs' => ['nullable', 'boolean'],
        ]);

        $update = [];
        if (! empty($validated['name'])) {
            $update['name'] = $validated['name'];
            $update['slug'] = Rank::uniqueSlug($project->id, $validated['name'], $rank->id);
        }
        if (array_key_exists('description', $validated)) {
            $update['description'] = $validated['description'];
        }
        if (! empty($validated['color'])) {
            $update['color'] = $validated['color'];
        }
        if (array_key_exists('manages_bugs', $validated) && $validated['manages_bugs'] !== null) {
            $update['manages_bugs'] = (bool) $validated['manages_bugs'];
        }

        if (! empty($update)) {
            $rank->update($update);
            ActivityLogger::log(
                $project,
                $request->user(),
                'rank_updated',
                sprintf('%s a modifié le rank « %s »', $request->user()->name, $rank->name),
                $rank,
                $update,
            );
        }

        return $this->apiOrBack($request, [
            'rank' => $this->serializeRank($rank->fresh(['members:id,name,email,avatar_path']), $request->user()),
        ]);
    }

    public function destroy(Request $request, Project $project, Rank $rank): JsonResponse|RedirectResponse
    {
        $this->ensureCanEdit($request, $project);
        abort_unless($rank->project_id === $project->id, 404);

        $rankId = $rank->id;
        $rank->delete();

        $project->ranks()
            ->orderBy('position')
            ->get()
            ->each(fn ($r, $idx) => $r->update(['position' => $idx]));

        return $this->apiOrBack($request, ['rank_id' => $rankId]);
    }

    public function addMember(Request $request, Project $project, Rank $rank): JsonResponse|RedirectResponse
    {
        $this->ensureCanManageRankMembers($request, $project, $rank);
        abort_unless($rank->project_id === $project->id, 404);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        abort_unless(
            $project->members()->whereKey($validated['user_id'])->exists(),
            422,
            'Cet utilisateur n\'est pas membre du projet.',
        );

        $rank->members()->syncWithoutDetaching([$validated['user_id']]);

        return $this->apiOrBack($request, [
            'rank' => $this->serializeRank($rank->fresh(['members:id,name,email,avatar_path']), $request->user()),
        ]);
    }

    public function removeMember(Request $request, Project $project, Rank $rank, int $userId): JsonResponse|RedirectResponse
    {
        $this->ensureCanManageRankMembers($request, $project, $rank);
        abort_unless($rank->project_id === $project->id, 404);

        $isResponsible = $rank->members()
            ->whereKey($userId)
            ->wherePivot('is_responsible', true)
            ->exists();

        if ($isResponsible && ! $request->user()->is_admin) {
            abort(403, 'Un responsable ne peut être retiré du rank que par un administrateur.');
        }

        $rank->members()->detach($userId);

        return $this->apiOrBack($request, [
            'rank' => $this->serializeRank($rank->fresh(['members:id,name,email,avatar_path']), $request->user()),
        ]);
    }

    /**
     * Ajoute ou retire le statut de responsable pour un membre du rang. Un rang
     * peut avoir plusieurs responsables ; le membre est attaché au rang si besoin.
     */
    public function toggleResponsible(Request $request, Project $project, Rank $rank): JsonResponse|RedirectResponse
    {
        $this->ensureCanEdit($request, $project);
        abort_unless($rank->project_id === $project->id, 404);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $userId = (int) $validated['user_id'];
        abort_unless(
            $project->members()->whereKey($userId)->exists(),
            422,
            'Cet utilisateur n\'est pas membre du projet.',
        );

        // On s'assure que le membre fait partie du rang, puis on bascule le flag.
        $rank->members()->syncWithoutDetaching([$userId]);

        $isResponsible = $rank->members()
            ->whereKey($userId)
            ->wherePivot('is_responsible', true)
            ->exists();

        $rank->members()->updateExistingPivot($userId, [
            'is_responsible' => ! $isResponsible,
        ]);

        return $this->apiOrBack($request, [
            'rank' => $this->serializeRank($rank->fresh(['members:id,name,email,avatar_path']), $request->user()),
        ]);
    }

    public function toggleBugs(Request $request, Project $project, Rank $rank): JsonResponse|RedirectResponse
    {
        $this->ensureCanEdit($request, $project);
        abort_unless($rank->project_id === $project->id, 404);

        $rank->update(['manages_bugs' => ! $rank->manages_bugs]);

        return $this->apiOrBack($request, [
            'rank' => $this->serializeRank($rank->fresh(['members:id,name,email,avatar_path']), $request->user()),
        ]);
    }

    private function ensureDefaultRanks(Project $project): void
    {
        if ($project->ranks()->exists()) {
            return;
        }
        foreach (Rank::defaultsFor($project->id) as $r) {
            $project->ranks()->create($r);
        }
    }

    private function ensureCanEdit(Request $request, Project $project): void
    {
        abort_unless($request->user()->is_admin, 403);
    }

    private function ensureCanManageRankMembers(Request $request, Project $project, Rank $rank): void
    {
        $user = $request->user();

        abort_unless(
            $user->is_admin || $this->userManagesRank($user, $rank),
            403,
        );
    }

    private function userManagesRank(User $user, Rank $rank): bool
    {
        return $rank->members()
            ->whereKey($user->id)
            ->wherePivot('is_responsible', true)
            ->exists();
    }

    private function serializeRank(Rank $rank, User $user): array
    {
        return [
            'id' => $rank->id,
            'name' => $rank->name,
            'slug' => $rank->slug,
            'color' => $rank->color,
            'description' => $rank->description,
            'manages_bugs' => (bool) $rank->manages_bugs,
            'position' => (int) $rank->position,
            'responsibles' => $rank->members
                ->filter(fn ($m) => (bool) $m->pivot->is_responsible)
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'avatar_url' => $m->avatar_url,
                ])
                ->values(),
            'members' => $rank->members->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'avatar_url' => $m->avatar_url,
                'is_responsible' => (bool) $m->pivot->is_responsible,
            ])->values(),
            'counts' => [
                'members' => $rank->members->count(),
                'tasks' => Task::query()
                    ->where('project_id', $rank->project_id)
                    ->whereHas('list', fn ($q) => $q->where('rank_id', $rank->id))
                    ->count(),
                'notes' => $rank->notes()->count(),
            ],
            'can_manage_members' => $user->is_admin || $this->userManagesRank($user, $rank),
        ];
    }
}
