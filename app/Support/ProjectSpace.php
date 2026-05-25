<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Rank;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

class ProjectSpace
{
    public const GLOBAL = 'global';

    public const FULL = 'full';

    public function __construct(
        public readonly string $key,
        public readonly ?int $rankId,
        public readonly bool $isGlobal,
        public readonly bool $isFull,
    ) {}

    public static function resolve(Request $request, Project $project, User $user): self
    {
        $key = (string) $request->query('space', self::GLOBAL);

        if ($key === self::FULL) {
            abort_unless($user->is_admin, 403);

            return new self($key, null, false, true);
        }

        if ($key === self::GLOBAL) {
            return new self($key, null, true, false);
        }

        $rank = $project->ranks()->where('slug', $key)->first();
        abort_unless($rank, 404, 'Espace introuvable.');

        if (! $user->is_admin) {
            $isRankMember = $rank->members()->whereKey($user->id)->exists();
            abort_unless($isRankMember, 403, 'Vous n\'avez pas accès à cet espace.');
        }

        return new self($key, $rank->id, false, false);
    }

    public function applyScope(Builder|Relation $query, string $column = 'rank_id'): Builder|Relation
    {
        if ($this->isFull) {
            return $query;
        }

        if ($this->isGlobal) {
            return $query->whereNull($column);
        }

        return $query->where($column, $this->rankId);
    }

    public function owns(?int $entityRankId): bool
    {
        if ($this->isFull) {
            return true;
        }

        if ($this->isGlobal) {
            return $entityRankId === null;
        }

        return $entityRankId === $this->rankId;
    }

    public function rankIdForCreate(): ?int
    {
        return $this->isFull || $this->isGlobal ? null : $this->rankId;
    }

    public function ensureResources(Project $project): void
    {
        if ($this->isFull) {
            return;
        }

        $rankId = $this->rankIdForCreate();

        if ($this->isGlobal) {
            if (! $project->lists()->whereNull('rank_id')->exists()) {
                $project->lists()->createMany(
                    collect(TaskList::defaultsFor($project->id))
                        ->map(fn ($l) => array_merge(
                            collect($l)->except('project_id')->all(),
                            ['rank_id' => null],
                        ))
                        ->all()
                );
            }

            if (! $project->sheets()->whereNull('rank_id')->exists()) {
                $project->sheets()->create([
                    'name' => 'Feuille 1',
                    'position' => 0,
                    'rows' => 50,
                    'cols' => 26,
                    'data' => new \stdClass,
                    'rank_id' => null,
                ]);
            }

            return;
        }

        if (! $project->lists()->where('rank_id', $rankId)->exists()) {
            $project->lists()->createMany(
                collect(TaskList::defaultsFor($project->id))
                    ->map(fn ($l) => array_merge(
                        collect($l)->except('project_id')->all(),
                        ['rank_id' => $rankId],
                    ))
                    ->all()
            );
        }

        if (! $project->sheets()->where('rank_id', $rankId)->exists()) {
            $project->sheets()->create([
                'name' => 'Feuille 1',
                'position' => 0,
                'rows' => 50,
                'cols' => 26,
                'data' => new \stdClass,
                'rank_id' => $rankId,
            ]);
        }
    }
}
