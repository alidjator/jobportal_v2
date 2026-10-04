<?php

namespace Src\Platform\Infrastructure\Email;

use Illuminate\Mail\Markdown;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Src\Platform\Application\Email\EmailBodyRenderer;

/**
 * Markdown to email. Raw HTML in the Markdown is escaped (shown as text, never executed) and unsafe links (`javascript:` and the
 * like) are refused. The resulting HTML is put in Laravel's email layout and theme with the CSS inlined.
 */
final readonly class CommonMarkEmailBodyRenderer implements EmailBodyRenderer
{
    public function __construct(private Markdown $markdown) {}

    public function html(string $markdown): string
    {
        return (string) $this->markdown->render('emails.templated', ['html' => $this->convert($markdown)]);
    }

    public function text(string $markdown): string
    {
        $html = $this->convert($markdown);
        $html = (string) preg_replace('/<a [^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $html);
        $html = (string) preg_replace('/<\/(p|h[1-6]|li|blockquote|ul|ol|tr)>|<br\s*\/?>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    private function convert(string $markdown): string
    {
        $environment = new Environment(['html_input' => 'escape', 'allow_unsafe_links' => false]);
        $environment->addExtension(new CommonMarkCoreExtension);

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }
}
