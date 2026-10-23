<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** The `applicant` block of `GET company/applications/{id}` is documented as a union (anonymised or full): the response keys and the OpenAPI document are pinned here. */
final class CompanyApplicantBlockShapeTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->buildApplicationFixtures();
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function detail(): TestResponse
    {
        return $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->recruiterToken);
    }

    /** @return list<string> the keys of the applicant block, sorted */
    private function applicantKeys(): array
    {
        $keys = array_keys((array) $this->detail()->assertOk()->json('data.applicant'));
        sort($keys);

        return $keys;
    }

    public function test_a_full_applicant_block_carries_the_profile_without_the_snapshot_version(): void
    {
        $this->verifyTheCompany();

        $this->assertSame([
            'age', 'availability', 'available_from', 'contact_hidden', 'educations', 'email', 'expected_salary', 'experience_level', 'experiences', 'headline', 'is_deleted',
            'languages', 'links', 'location', 'name', 'phone', 'skills', 'summary', 'whatsapp',
        ], $this->applicantKeys());
        $this->detail()
            ->assertJsonPath('data.applicant.is_deleted', false)
            ->assertJsonPath('data.applicant.contact_hidden', false)
            ->assertJsonPath('data.applicant.name', 'Siti Rahma')
            ->assertJsonPath('data.applicant.email', 'siti@example.test')
            ->assertJsonPath('data.applicant.phone', '081234567890')
            ->assertJsonPath('data.applicant.experiences.0.job_title', 'Backend Developer')
            ->assertJsonPath('data.applicant.experiences.0.start', '2022-03')
            ->assertJsonPath('data.applicant.experiences.0.job_type', null)
            ->assertJsonStructure(['data' => ['applicant' => ['age', 'location' => ['province_id', 'city_id', 'province', 'city', 'subdistrict', 'village', 'postal_code', 'address_line']]]]);
    }

    public function test_a_hidden_contact_keeps_the_keys_and_nulls_the_contact_fields(): void
    {
        $this->assertContains('contact_hidden', $this->applicantKeys());
        $this->detail()
            ->assertJsonPath('data.applicant.contact_hidden', true)
            ->assertJsonPath('data.applicant.email', null)
            ->assertJsonPath('data.applicant.phone', null)
            ->assertJsonPath('data.applicant.whatsapp', null)
            ->assertJsonPath('data.applicant.name', 'Siti Rahma');
    }

    public function test_an_anonymised_applicant_holds_only_the_deleted_flag_and_a_null_name(): void
    {
        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['profile_snapshot' => null]);

        $this->assertSame(['is_deleted', 'name'], $this->applicantKeys());
        $this->detail()->assertJsonPath('data.applicant.is_deleted', true)->assertJsonPath('data.applicant.name', null);
    }

    public function test_the_document_describes_the_block_as_a_union_of_closed_objects(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'openapi');
        Artisan::call('scramble:export', ['--path' => $path]);
        $applicant = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)['components']['schemas']['CompanyApplicationDetailResource']['properties']['applicant'];
        unlink($path);

        $this->assertCount(2, $applicant['anyOf']);
        [$anonymised, $full] = $applicant['anyOf'];
        $this->assertSame([true], $anonymised['properties']['is_deleted']['enum']);
        $this->assertSame([false], $full['properties']['is_deleted']['enum']);
        $this->assertSame(['is_deleted', 'name'], $anonymised['required']);
        $this->assertSame(['is_deleted', 'contact_hidden'], $full['required']);
        $this->assertSame('array', $full['properties']['educations']['type']);
        $this->assertSame('object', $full['properties']['educations']['items']['type']);
        $this->assertArrayHasKey('migrated_at', $full['properties']);
        $this->assertArrayNotHasKey('version', $full['properties']);
        $this->assertArrayNotHasKey('additionalProperties', $full);
    }
}
