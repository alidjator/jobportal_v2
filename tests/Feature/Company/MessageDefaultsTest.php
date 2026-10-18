<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-interview-calendar.md` section 6: the default texts of a company and the link rule of every note sent to a candidate. */
final class MessageDefaultsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        DB::table('company_companies')->where('id', $this->companyId)->update(['website' => 'https://www.maju.example']);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function actionCount(): int
    {
        return DB::table('platform_audit_logs')->where('action', 'company.message_defaults_changed')->count();
    }

    public function test_a_company_starts_without_defaults_and_any_member_reads_them(): void
    {
        $empty = ['interview' => null, 'assessment' => null, 'offer' => null, 'applied' => null, 'hired' => null];

        $this->api('GET', '/company/message-defaults', token: $this->adminToken)->assertOk()->assertExactJson(['data' => $empty]);
        $this->api('GET', '/company/message-defaults', token: $this->recruiterToken)->assertOk()->assertExactJson(['data' => $empty]);
        $this->api('GET', '/company/message-defaults', token: $this->seekerToken)->assertForbidden();
    }

    public function test_the_admin_sets_the_texts_and_only_the_scenarios_in_the_request_change(): void
    {
        $this->api('PUT', '/company/message-defaults', ['interview' => '  Please bring your ID card.  ', 'offer' => 'Welcome aboard.'], $this->adminToken)
            ->assertOk()->assertExactJson(['data' => ['interview' => 'Please bring your ID card.', 'assessment' => null, 'offer' => 'Welcome aboard.', 'applied' => null, 'hired' => null]]);
        $this->assertSame(['scenarios' => ['interview', 'offer']], json_decode((string) DB::table('platform_audit_logs')->where('action', 'company.message_defaults_changed')->value('metadata'), true));

        // A scenario that is missing stays; a blank text or null clears one.
        $this->api('PUT', '/company/message-defaults', ['assessment' => 'Use a quiet room.', 'offer' => '   '], $this->adminToken)
            ->assertOk()->assertExactJson(['data' => ['interview' => 'Please bring your ID card.', 'assessment' => 'Use a quiet room.', 'offer' => null, 'applied' => null, 'hired' => null]]);
        $this->api('PUT', '/company/message-defaults', ['interview' => null], $this->adminToken)->assertOk()->assertJsonPath('data.interview', null);
        $this->api('GET', '/company/message-defaults', token: $this->recruiterToken)->assertOk()->assertJsonPath('data.assessment', 'Use a quiet room.');
        $this->assertSame(3, $this->actionCount());

        // The same values again change and audit nothing.
        $this->api('PUT', '/company/message-defaults', ['assessment' => 'Use a quiet room.'], $this->adminToken)->assertOk();
        $this->api('PUT', '/company/message-defaults', [], $this->adminToken)->assertOk();
        $this->assertSame(3, $this->actionCount());
    }

    public function test_only_a_member_who_may_change_the_profile_sets_them(): void
    {
        $this->api('PUT', '/company/message-defaults', ['interview' => 'Hello'], $this->recruiterToken)->assertForbidden()->assertJsonPath('permission', 'company.profile');

        $editor = $this->memberWith(['company.profile'], 'editor@example.test');
        $this->api('PUT', '/company/message-defaults', ['interview' => 'Hello'], $editor['token'])->assertOk();
        $this->api('PUT', '/company/message-defaults', ['interview' => 'Hello'], $this->seekerToken)->assertForbidden();
    }

    public function test_the_length_limits_are_a_thousand_characters_and_two_thousand_for_an_offer(): void
    {
        $this->api('PUT', '/company/message-defaults', ['interview' => str_repeat('a', 1001)], $this->adminToken)->assertStatus(422)->assertJsonValidationErrors(['interview']);
        $this->api('PUT', '/company/message-defaults', ['assessment' => str_repeat('a', 1001)], $this->adminToken)->assertStatus(422)->assertJsonValidationErrors(['assessment']);
        $this->api('PUT', '/company/message-defaults', ['applied' => str_repeat('a', 1001)], $this->adminToken)->assertStatus(422)->assertJsonValidationErrors(['applied']);
        $this->api('PUT', '/company/message-defaults', ['hired' => str_repeat('a', 1001)], $this->adminToken)->assertStatus(422)->assertJsonValidationErrors(['hired']);
        $this->api('PUT', '/company/message-defaults', ['offer' => str_repeat('a', 2001)], $this->adminToken)->assertStatus(422)->assertJsonValidationErrors(['offer']);

        $this->api('PUT', '/company/message-defaults', ['interview' => str_repeat('a', 1000), 'offer' => str_repeat('b', 2000)], $this->adminToken)->assertOk();
    }

    public function test_a_default_with_a_foreign_link_or_address_is_refused_but_the_company_own_domain_and_phone_numbers_pass(): void
    {
        foreach (['Pay at https://evil.example/pay', 'Mail hr@gmail.com', 'See bit.ly/abc', 'Open evil.com/apply'] as $text) {
            $this->api('PUT', '/company/message-defaults', ['interview' => $text], $this->adminToken)
                ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed')->assertJsonStructure(['errors' => ['interview']]);
        }

        $this->assertSame(0, $this->actionCount());

        $this->api('PUT', '/company/message-defaults', ['interview' => 'Read https://maju.example/careers or mail hr@maju.example, or call 021 555 0100.'], $this->adminToken)->assertOk();

        // Without a website every link is refused.
        DB::table('company_companies')->where('id', $this->companyId)->update(['website' => null]);
        $this->api('PUT', '/company/message-defaults', ['interview' => 'Read https://maju.example/careers'], $this->adminToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
    }
}
