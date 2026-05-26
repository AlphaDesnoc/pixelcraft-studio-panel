<?php

namespace App\Support;

class MarkdownParser
{
    public static function toHtml(string $body): string
    {
        $escaped = e($body);

        $escaped = preg_replace(
            '/\*\*(.+?)\*\*/s',
            '<strong>$1</strong>',
            $escaped,
        ) ?? $escaped;

        $escaped = preg_replace(
            '/`([^`]+)`/',
            '<code class="rounded bg-muted/60 px-1 py-0.5 font-mono text-[0.85em]">$1</code>',
            $escaped,
        ) ?? $escaped;

        $escaped = preg_replace(
            '/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/',
            '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-primary underline underline-offset-2">$1</a>',
            $escaped,
        ) ?? $escaped;

        return nl2br($escaped, false);
    }
}
