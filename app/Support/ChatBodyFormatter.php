<?php

namespace App\Support;

class ChatBodyFormatter
{
    public static function toHtml(string $body, array $mentions = []): string
    {
        $html = MarkdownParser::toHtml($body);

        $rankSlugs = collect($mentions)
            ->filter(fn (array $mention) => ($mention['type'] ?? 'user') === 'rank')
            ->pluck('slug')
            ->filter()
            ->unique()
            ->sortByDesc(fn (string $slug) => strlen($slug))
            ->values();

        foreach ($rankSlugs as $slug) {
            $pattern = '/@('.preg_quote($slug, '/').')(?![a-z0-9._-])/i';
            $html = preg_replace(
                $pattern,
                '<span class="rounded bg-violet-500/20 px-1 font-medium text-violet-300">@$1</span>',
                $html,
            ) ?? $html;
        }

        return preg_replace(
            '/@([a-z0-9._-]{2,60})/i',
            '<span class="rounded bg-primary/20 px-1 font-medium text-primary">@$1</span>',
            $html,
        ) ?? $html;
    }
}
