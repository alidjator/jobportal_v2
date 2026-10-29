<?php

namespace Tests\Feature\Assessment;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/**
 * The identity of an institution (address, practice licence, contact, logo) and the signature image of a psychologist
 * (`docs/design/phase-8-provider-institutions.md` section 3, the part before the report prints them): leads edit the identity, any psychologist of the institution reads it, each psychologist
 * keeps their own signature.
 */
final class ProviderInstitutionTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private int $leadId;

    private string $memberToken;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        $this->buildApplicationFixtures();
        $this->addAssessmentFixtures();

        // The psychologist of the first institution leads it; a colleague is a member.
        $this->leadId = (int) DB::table('assessment_psychologists')->where('provider_id', $this->providerId)->value('id');
        DB::table('assessment_psychologists')->where('id', $this->leadId)->update(['role' => 'lead']);
        $this->memberToken = $this->colleagueOf($this->providerId, 'rekan@example.test');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- helpers -----

    private function png(int $width = 120, int $height = 80): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 20, 20, 20));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function upload(string $uri, ?string $contents = null, ?string $token = null, string $name = 'image.png'): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->psychologistToken)->post('/api/v1/provider/'.$uri, [
            'file' => UploadedFile::fake()->createWithContent($name, $contents ?? $this->png()),
        ], ['Accept' => 'application/json']);
    }

    // ----- the identity -----

    public function test_a_lead_edits_the_identity_and_any_psychologist_of_the_institution_reads_it(): void
    {
        $this->provider('PUT', 'institution', [
            'address' => 'Jl. Merdeka 10, Bandung', 'practice_licence_number' => 'SIP-123', 'phone' => '022-555', 'email' => 'info@lembaga.test',
        ])->assertOk()->assertJsonPath('data.address', 'Jl. Merdeka 10, Bandung')->assertJsonPath('data.has_logo', false);

        $this->provider('GET', 'institution', [], $this->memberToken)
            ->assertOk()
            ->assertJsonPath('data.name', 'Lembaga Psikologi Contoh')
            ->assertJsonPath('data.practice_licence_number', 'SIP-123')
            ->assertJsonPath('data.phone', '022-555')
            ->assertJsonPath('data.email', 'info@lembaga.test');
    }

    public function test_only_the_fields_that_are_sent_change_and_a_blank_one_clears(): void
    {
        $this->provider('PUT', 'institution', ['address' => 'Jl. Merdeka 10', 'phone' => '022-555'])->assertOk();
        $this->provider('PUT', 'institution', ['phone' => ''])->assertOk()->assertJsonPath('data.address', 'Jl. Merdeka 10')->assertJsonPath('data.phone', null);
        $this->provider('PUT', 'institution', [])->assertOk()->assertJsonPath('data.address', 'Jl. Merdeka 10');
    }

    public function test_the_identity_is_validated(): void
    {
        $this->provider('PUT', 'institution', ['email' => 'not-an-email'])->assertUnprocessable();
        $this->provider('PUT', 'institution', ['address' => str_repeat('a', 501)])->assertUnprocessable();
        $this->provider('PUT', 'institution', ['phone' => str_repeat('1', 41)])->assertUnprocessable();
    }

    public function test_a_member_cannot_change_the_identity_or_the_logo(): void
    {
        $this->provider('PUT', 'institution', ['address' => 'X'], $this->memberToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-lead');
        $this->upload('institution/logo', null, $this->memberToken)->assertForbidden();
        $this->provider('DELETE', 'institution/logo', [], $this->memberToken)->assertForbidden();
        $this->assertNull(DB::table('assessment_providers')->where('id', $this->providerId)->value('address'));
    }

    public function test_an_institution_never_reads_or_changes_another(): void
    {
        $this->provider('PUT', 'institution', ['address' => 'Jl. Merdeka 10'])->assertOk();

        $this->provider('GET', 'institution', [], $this->otherPsychologistToken)->assertOk()->assertJsonPath('data.address', null)->assertJsonPath('data.name', 'Biro Psikologi Lain');
    }

    public function test_the_platform_admin_edits_the_identity_through_the_provider_update(): void
    {
        $this->assessmentAdmin('PUT', "providers/{$this->providerId}", [
            'code' => 'LPCT', 'name' => 'Lembaga Psikologi Contoh', 'legal_name' => 'PT Lembaga Psikologi Contoh', 'address' => 'Jl. Asia Afrika 1', 'email' => 'admin@lembaga.test',
        ])->assertOk()->assertJsonPath('data.address', 'Jl. Asia Afrika 1')->assertJsonPath('data.email', 'admin@lembaga.test');

        // Without the fields the identity stays.
        $this->assessmentAdmin('PUT', "providers/{$this->providerId}", [
            'code' => 'LPCT', 'name' => 'Lembaga Psikologi Contoh', 'legal_name' => 'PT Lembaga Psikologi Contoh',
        ])->assertOk()->assertJsonPath('data.address', 'Jl. Asia Afrika 1');
    }

    // ----- the logo -----

    public function test_a_lead_puts_replaces_and_takes_off_the_logo(): void
    {
        $this->upload('institution/logo')->assertNoContent();

        $first = (string) DB::table('assessment_providers')->where('id', $this->providerId)->value('logo_path');
        Storage::assertExists($first);
        $this->provider('GET', 'institution')->assertJsonPath('data.has_logo', true);
        $this->provider('GET', 'institution/logo', [], $this->memberToken)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->upload('institution/logo', $this->png(200, 100))->assertNoContent();
        $second = (string) DB::table('assessment_providers')->where('id', $this->providerId)->value('logo_path');
        $this->assertNotSame($first, $second);
        Storage::assertMissing($first);
        Storage::assertExists($second);

        $this->provider('DELETE', 'institution/logo')->assertNoContent();
        Storage::assertMissing($second);
        $this->provider('GET', 'institution/logo')->assertNotFound();
        $this->provider('GET', 'institution')->assertJsonPath('data.has_logo', false);
    }

    public function test_the_logo_is_checked_by_its_content_and_its_size_and_is_encoded_again(): void
    {
        $this->upload('institution/logo', 'not an image at all')->assertUnprocessable()->assertJsonPath('type', 'urn:onesis:error:invalid-image');
        $this->upload('institution/logo', $this->png().str_repeat('a', 2 * 1024 * 1024))->assertUnprocessable()->assertJsonPath('type', 'urn:onesis:error:file-too-large');
        $this->assertNull(DB::table('assessment_providers')->where('id', $this->providerId)->value('logo_path'));
        $this->assertCount(0, Storage::allFiles('assessment/institution-logos'));

        // A picture larger than the box is shrunk, not refused.
        $this->upload('institution/logo', $this->png(1600, 800))->assertNoContent();
        $path = (string) DB::table('assessment_providers')->where('id', $this->providerId)->value('logo_path');
        [$width, $height] = getimagesizefromstring((string) Storage::get($path)) ?: [0, 0];
        $this->assertLessThanOrEqual(800, $width);
        $this->assertLessThanOrEqual(400, $height);
    }

    // ----- the signature -----

    public function test_a_psychologist_keeps_their_own_signature_image(): void
    {
        $this->upload('me/signature', null, $this->memberToken)->assertNoContent();

        $path = (string) DB::table('assessment_psychologists')->where('user_id', DB::table('identity_users')->where('email', 'rekan@example.test')->value('id'))->value('signature_path');
        Storage::assertExists($path);
        $this->provider('GET', 'me')->assertJsonPath('data.has_signature', false);
        $this->provider('GET', 'me', [], $this->memberToken)->assertJsonPath('data.has_signature', true);
        $this->provider('GET', 'me/signature', [], $this->memberToken)->assertOk();

        // The lead has none, and cannot read the colleague's.
        $this->provider('GET', 'me/signature')->assertNotFound();

        $this->provider('DELETE', 'me/signature', [], $this->memberToken)->assertNoContent();
        Storage::assertMissing($path);
        $this->provider('GET', 'me', [], $this->memberToken)->assertJsonPath('data.has_signature', false);
    }

    public function test_the_signature_is_checked_by_its_content_and_its_size(): void
    {
        $this->upload('me/signature', 'not an image')->assertUnprocessable()->assertJsonPath('type', 'urn:onesis:error:invalid-image');
        $this->upload('me/signature', $this->png().str_repeat('a', 1024 * 1024))->assertUnprocessable()->assertJsonPath('type', 'urn:onesis:error:file-too-large');
        $this->assertCount(0, Storage::allFiles('assessment/signatures'));
    }

    public function test_the_endpoints_need_a_signed_in_psychologist(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withoutToken()->getJson('/api/v1/provider/institution')->assertUnauthorized();
        $this->withoutToken()->getJson('/api/v1/provider/me/signature')->assertUnauthorized();
    }
}
