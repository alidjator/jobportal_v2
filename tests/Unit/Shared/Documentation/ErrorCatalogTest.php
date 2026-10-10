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

    public function test_the_catalog_file_is_up_to_date(): void
    {
        $this->artisan('api:error-catalog', ['--check' => true])->assertSuccessful();
    }
}
