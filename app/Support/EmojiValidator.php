<?php

namespace App\Support;

class EmojiValidator
{
    public static function isReactionEmoji(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 32) {
            return false;
        }

        if (preg_match('/[\p{L}\p{N}<>&]/u', $value)) {
            return false;
        }

        return (bool) preg_match(
            '/[\p{Extended_Pictographic}\x{FE0F}\x{200D}\x{20E3}\x{1F3FB}-\x{1F3FF}\x{1F1E6}-\x{1F1FF}]/u',
            $value,
        );
    }
}
