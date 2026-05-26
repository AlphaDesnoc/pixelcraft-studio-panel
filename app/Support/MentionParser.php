<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MentionParser
{
    /** @return array<int, array{id: int, pseudo: string}> */
    public static function extract(string $body, Collection $candidates): array
    {
        if (! preg_match_all('/@([a-z0-9._-]{2,60})/i', $body, $matches)) {
            return [];
        }

        $pseudos = collect($matches[1])
            ->map(fn (string $pseudo) => Str::lower($pseudo))
            ->unique()
            ->values();

        return $candidates
            ->filter(function (User $user) use ($pseudos) {
                $pseudo = Str::before(Str::lower($user->email), '@');

                return $pseudos->contains($pseudo);
            })
            ->map(fn (User $user) => [
                'id' => $user->id,
                'pseudo' => Str::before($user->email, '@'),
            ])
            ->values()
            ->all();
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
