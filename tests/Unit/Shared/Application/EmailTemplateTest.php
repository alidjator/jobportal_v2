<?php

namespace Tests\Unit\Shared\Application;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\Shared\Application\Email\EmailPlaceholders;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

final class EmailTemplateTest extends TestCase
{
    /** @param  array<string, string>  $body */
    private function definition(string $key = 'probe.welcome', array $body = ['id' => 'Halo {{name}} {{code}}', 'en' => 'Hello {{ name }} {{code}}'], ?array $subject = null, bool $codeRequired = true): EmailTemplateDefinition
    {
        return new EmailTemplateDefinition(
            $key,
            'A probe template',
            [new EmailVariable('name', 'The name', 'Siti'), new EmailVariable('code', 'The code', '123456', required: $codeRequired)],
            TranslatedText::fromArray($subject ?? ['id' => 'Selamat datang', 'en' => 'Welcome']),
            TranslatedText::fromArray($body),
        );
    }

    public function test_placeholders_are_found_once_in_order_and_may_have_spaces(): void
    {
        $this->assertSame(['name', 'code'], EmailPlaceholders::in('Hi {{name}}, {{ code }} and {{name}} again, {{code}}'));
        $this->assertSame([], EmailPlaceholders::in('No variables, {single} braces, {{ 1+1 }} and {{Upper}}'));
    }

    public function test_values_are_substituted_and_unknown_or_missing_ones_stay(): void
    {
        $text = EmailPlaceholders::intoBody('Hi {{name}} {{ code }} {{other}}', ['name' => 'Siti', 'code' => '049213']);

        $this->assertSame('Hi Siti 049213 {{other}}', $text);
        $this->assertSame('Hi {{name}}', EmailPlaceholders::intoBody('Hi {{name}}', []));
    }

    public function test_a_value_cannot_change_the_markdown_around_it(): void
    {
        $text = EmailPlaceholders::intoBody('Hi {{name}}', ['name' => '*Siti* [x](http://evil) <b>#1</b> `y` _z_']);

        $this->assertSame('Hi \\*Siti\\* \\[x\\]\\(http://evil\\) \\<b\\>\\#1\\</b\\> \\`y\\` \\_z\\_', $text);
    }

    public function test_a_value_that_looks_like_a_placeholder_is_not_expanded_again(): void
    {
        $this->assertSame('\\{\\{code\\}\\} 049213', EmailPlaceholders::intoBody('{{name}} {{code}}', ['name' => '{{code}}', 'code' => '049213']));
    }

    public function test_a_subject_is_one_line_and_not_markdown_escaped(): void
    {
        $subject = EmailPlaceholders::intoSubject('Code for {{name}}', ['name' => "Siti\r\nBcc: evil@example.test"]);

        $this->assertSame('Code for Siti Bcc: evil@example.test', $subject);
        $this->assertSame('A *b*', EmailPlaceholders::intoSubject('A {{name}}', ['name' => '*b*']));
    }

    public function test_the_catalog_registers_templates_sorted_by_key(): void
    {
        $catalog = new EmailTemplateCatalog;
        $catalog->add($this->definition('probe.zulu'), $this->definition('probe.alpha'));

        $this->assertSame(['probe.alpha', 'probe.zulu'], array_map(fn ($d) => $d->key, $catalog->all()));
        $this->assertNotNull($catalog->find('probe.zulu')->variable('code'));
        $this->assertSame(['name', 'code'], $catalog->find('probe.zulu')->variableNames());
        $this->assertSame(['code'], $catalog->find('probe.zulu')->requiredVariableNames());
        $this->assertNull($catalog->find('probe.none'));
    }

    public function test_a_bad_key_or_a_duplicate_is_refused(): void
    {
        $catalog = new EmailTemplateCatalog;

        foreach (['welcome', 'Probe.welcome', 'probe.wel-come'] as $key) {
            try {
                $catalog->add($this->definition($key));
                $this->fail("{$key} must be refused.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $catalog->add($this->definition());
        $this->expectException(InvalidArgumentException::class);
        $catalog->add($this->definition());
    }

    public function test_a_default_that_breaks_the_rules_is_caught_at_registration(): void
    {
        $catalog = new EmailTemplateCatalog;

        foreach ([
            'undeclared variable in the body' => ['body' => ['id' => 'Halo {{code}} {{typo}}', 'en' => 'Hello {{code}}']],
            'required variable missing' => ['body' => ['id' => 'Halo {{name}}', 'en' => 'Hello {{name}} {{code}}']],
            'undeclared variable in the subject' => ['body' => ['id' => 'Halo {{code}}', 'en' => 'Hello {{code}}'], 'subject' => ['id' => 'Hai {{x}}', 'en' => 'Hi']],
        ] as $name => $case) {
            try {
                $catalog->add($this->definition('probe.bad', $case['body'], $case['subject'] ?? null));
                $this->fail("{$name} must be refused.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_default_must_exist_in_every_language(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->definition('probe.bad', ['id' => 'Halo {{code}}']);
    }
}
