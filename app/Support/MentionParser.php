<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Rank;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MentionParser
{
    /** @return array<int, array{id: int, pseudo: string, type: string}> */
    public static function extract(string $body, Collection $candidates, ?Collection $ranks = null): array
    {
        $rankMentions = $ranks !== null
            ? self::extractRanks($body, $ranks)
            : [];

        $reservedSlugs = collect($rankMentions)
            ->pluck('slug')
            ->map(fn (string $slug) => Str::lower($slug));

        $userMentions = self::extractUsers($body, $candidates, $reservedSlugs);

        return array_merge($rankMentions, $userMentions);
    }

    /** @return array<int, array{id: int, slug: string, name: string, type: string}> */
    public static function extractRanks(string $body, Collection $ranks): array
    {
        if (! preg_match_all('/@([a-z0-9._-]{2,60})/i', $body, $matches)) {
            return [];
        }

        $tokens = collect($matches[1])
            ->map(fn (string $token) => Str::lower($token))
            ->unique()
            ->values();

        return $ranks
            ->filter(function (Rank $rank) use ($tokens) {
                return $tokens->contains(Str::lower($rank->slug));
            })
            ->map(fn (Rank $rank) => [
                'type' => 'rank',
                'id' => $rank->id,
                'slug' => $rank->slug,
                'name' => $rank->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, string>  $reservedSlugs
     * @return array<int, array{id: int, pseudo: string, type: string}>
     */
    public static function extractUsers(
        string $body,
        Collection $candidates,
        ?Collection $reservedSlugs = null,
    ): array {
        $reservedSlugs ??= collect();
        if (! preg_match_all('/@([a-z0-9._-]{2,60})/i', $body, $matches)) {
            return [];
        }

        $pseudos = collect($matches[1])
            ->map(fn (string $pseudo) => Str::lower($pseudo))
            ->reject(fn (string $pseudo) => $reservedSlugs->contains($pseudo))
            ->unique()
            ->values();

        return $candidates
            ->filter(function (User $user) use ($pseudos) {
                $pseudo = Str::before(Str::lower($user->email), '@');

                return $pseudos->contains($pseudo);
            })
            ->map(fn (User $user) => [
                'type' => 'user',
                'id' => $user->id,
                'pseudo' => Str::before($user->email, '@'),
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int, int> */
    public static function notifiedUserIds(Project $project, array $mentions): Collection
    {
        $ids = collect();

        foreach ($mentions as $mention) {
            if (($mention['type'] ?? 'user') === 'rank') {
                $rank = $project->ranks()->find($mention['id'] ?? null);
                if ($rank) {
                    $ids = $ids->merge($rank->members()->pluck('users.id'));
                }

                continue;
            }

            if (isset($mention['id'])) {
                $ids->push((int) $mention['id']);
            }
        }

        return $ids->unique()->values();
    }

    public static function highlightHtml(string $body): string
    {
        $escaped = e($body);

        return preg_replace(
            '/@([a-z0-9._-]{2,60})/i',
            '<span class="rounded bg-primary/20 px-1 font-medium text-primary">@$1</span>',
            $escaped,
        ) ?? $escaped;
    }
}
