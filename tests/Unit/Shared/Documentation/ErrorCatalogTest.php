<?php

namespace Tests\Unit\Shared\Documentation;

use Src\Shared\Infrastructure\Documentation\ErrorCatalog;
use Tests\TestCase;

final class ErrorCatalogTest extends TestCase
{
    /** @return array<string, array{code: string, statuses: list<int>, contexts: list<string>, message: string}> */
    private function byCode(): array
    {
        $entries = [];

        foreach ((new ErrorCatalog(base_path('src')))->entries() as $entry) {
            $entries[$entry['code']] = $entry;
        }

        return $entries;
    }

    public function test_codes_are_read_from_literals_constants_defaults_and_helper_calls(): void
    {
        $entries = $this->byCode();

        $this->assertSame([422], $entries['newsletter-token-invalid']['statuses'], 'a code held by a class constant');
        $this->assertSame([503], $entries['training-unavailable']['statuses'], 'a constant of an interface the class implements');
        $this->assertSame([429], $entries['cv-limit']['statuses'], 'a code at the third position of a throttle error');
        $this->assertSame([429], $entries['too-many-requests']['statuses']);
        $this->assertSame([422], $entries['validation-failed']['statuses']);
        $this->assertContains('Newsletter', $entries['newsletter-no-recipients']['contexts']);
        $this->assertSame([409], $entries['newsletter-no-recipients']['statuses']);
    }

    public function test_a_code_raised_with_two_statuses_lists_both(): void
    {
        $this->assertSame([403, 409], $this->byCode()['email-not-verified']['statuses']);
    }

    public function test_message_templates_are_read_from_literals_helpers_lists_and_calls(): void
    {
        $result = (new ErrorCatalog(base_path('src')))->templates();
        $templates = array_flip($result['templates']);

        $this->assertSame([], $result['unresolved'], 'every message expression is read');
        $this->assertArrayHasKey('The score must be a whole number from 0 to :points.', $templates, 'a template with a parameter');
        $this->assertArrayHasKey('Job not found: :id', $templates, 'an entity filled in from the calls of EntityNotFound::of');
        $this->assertArrayHasKey('Only an administrator can create a group.', $templates, 'a helper message filled in from its calls in other files');
        $this->assertArrayHasKey('The section ":section" needs at least one question.', $templates, 'an ErrorMessage in a list a helper method returns');
        $this->assertArrayHasKey('The duration must be between :min and :max minutes.', $templates, 'a message of an `errors` list chosen by a match');
        $this->assertArrayHasKey('The search can have at most :max words.', $templates, 'a closure rule of a form request');
        $this->assertArrayHasKey('Not Found', $templates, 'a status text used as the title');
    }

    public function test_the_catalog_file_is_up_to_date(): void
    {
        $this->artisan('api:error-catalog', ['--check' => true])->assertSuccessful();
    }
}
