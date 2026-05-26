<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorRecovery
{
    /** @return array<int, string> */
    public static function generatePlainCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::upper(Str::random(4).'-'.Str::random(4)))
            ->all();
    }

    /** @param  array<int, string>  $plainCodes */
    public static function hashCodes(array $plainCodes): array
    {
        return array_map(fn (string $code) => Hash::make(str_replace('-', '', $code)), $plainCodes);
    }

    /** @param  array<int, string>  $hashedCodes */
    public static function verifyAndConsume(array &$hashedCodes, string $input): bool
    {
        $normalized = strtoupper(str_replace([' ', '-'], '', trim($input)));

        foreach ($hashedCodes as $index => $hash) {
            if (Hash::check($normalized, $hash)) {
                unset($hashedCodes[$index]);
                $hashedCodes = array_values($hashedCodes);

                return true;
            }
        }

        return false;
    }
}
