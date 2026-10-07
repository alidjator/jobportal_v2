<?php

namespace Src\LegacyImport\Application;

use League\HTMLToMarkdown\HtmlConverter;

/**
 * HTML from a rich-text editor as Markdown: paragraphs, lists, bold, italic, headings and safe links are kept; scripts, styles, images, attributes and tags
 * the converter does not know are dropped (their text stays). A link is kept only when it points at `http`, `https` or `mailto`; any other link keeps its text only.
 */
final class LegacyMarkdown
{
    public static function from(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $converter = new HtmlConverter([
            'strip_tags' => true,
            'remove_nodes' => 'script style img iframe object embed',
            'header_style' => 'atx',
            'hard_break' => true,
            'use_autolinks' => false,
        ]);

        $markdown = $converter->convert('<div>'.$html.'</div>');
        // The converter leaves entities encoded; they are decoded for readable text, and a decoded `<` is escaped so that no raw tag can appear in the Markdown.
        $markdown = str_replace('<', '\<', html_entity_decode($markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $markdown = (string) preg_replace_callback('/(?<!\!)\[([^\]]*)\]\(((?:[^()\s]|\([^()\s]*\))*)(?:\s+"[^"]*")?\)/u', static function (array $match): string {
            $scheme = parse_url($match[2], PHP_URL_SCHEME);

            return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https', 'mailto'], true) ? $match[0] : $match[1];
        }, $markdown);
        $markdown = (string) preg_replace('/[ \t\x{00A0}]+\n/u', "\n", $markdown);
        $markdown = trim((string) preg_replace("/\n{3,}/", "\n\n", $markdown));

        return $markdown === '' ? null : $markdown;
    }
}
