<?php

namespace App\Support;

class ChatBodyFormatter
{
    public static function toHtml(string $body): string
    {
        $html = MarkdownParser::toHtml($body);

        return preg_replace(
            '/@([a-z0-9._-]{2,60})/i',
            '<span class="rounded bg-primary/20 px-1 font-medium text-primary">@$1</span>',
            $html,
        ) ?? $html;
    }
}
