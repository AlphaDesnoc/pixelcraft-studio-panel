<?php

namespace App\Support;

use App\Models\Project;

/**
 * Paliers d'accréditation hiérarchiques d'un projet.
 *
 * Un palier est un tableau ['value' => int, 'name' => string, 'color' => string].
 * Plus la valeur est élevée, plus le niveau est restrictif. Un dossier de niveau
 * N n'est accessible qu'aux utilisateurs de clairance >= N.
 */
class AccessLevels
{
    /** @return list<array{value:int,name:string,color:string}> */
    public static function defaults(): array
    {
        return [
            ['value' => 0, 'name' => 'Public', 'color' => '#64748b'],
            ['value' => 1, 'name' => 'Confidentiel', 'color' => '#f59e0b'],
            ['value' => 2, 'name' => 'Secret', 'color' => '#ef4444'],
            ['value' => 3, 'name' => 'Très secret', 'color' => '#7c3aed'],
        ];
    }

    /** @return list<array{value:int,name:string,color:string}> */
    public static function forProject(Project $project): array
    {
        $defined = $project->access_levels;

        if (! is_array($defined) || $defined === []) {
            return self::defaults();
        }

        // Normalise et trie par valeur croissante.
        $levels = collect($defined)
            ->map(fn ($l) => [
                'value' => (int) ($l['value'] ?? 0),
                'name' => (string) ($l['name'] ?? ('Niveau '.($l['value'] ?? 0))),
                'color' => (string) ($l['color'] ?? '#64748b'),
            ])
            ->sortBy('value')
            ->values()
            ->all();

        return $levels;
    }

    /** Valeurs autorisées (pour la validation). @return list<int> */
    public static function values(Project $project): array
    {
        return array_map(fn ($l) => $l['value'], self::forProject($project));
    }

    /** Valeur du palier le plus élevé. */
    public static function max(Project $project): int
    {
        return collect(self::forProject($project))->max('value') ?? 0;
    }

    /** Restreint une valeur à l'intervalle des paliers définis. */
    public static function clamp(Project $project, int $value): int
    {
        $values = self::values($project);
        $min = min($values);
        $max = max($values);

        return max($min, min($max, $value));
    }

    public static function isValid(Project $project, int $value): bool
    {
        return in_array($value, self::values($project), true);
    }
}
