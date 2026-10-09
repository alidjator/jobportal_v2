<?php

namespace Tests\Feature\JobSeeker;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\JobSeeker\Application\JobSeekerSettings;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class MyProfileTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private int $userId;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = $this->account('job_seeker', 'siti@example.test');
        $this->token = $this->tokenFor($this->userId);
    }

    private function account(string $type, string $email): int
    {
        return (int) UserModel::query()->create(['type' => $type, 'name' => 'Siti', 'email' => $email, 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00'])->id;
    }

    private function tokenFor(int $userId): string
    {
        return UserModel::query()->findOrFail($userId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1'.$uri, $body);
    }

    private function profileRow(?int $userId = null): ?object
    {
        return DB::table('jobseeker_profiles')->where('user_id', $userId ?? $this->userId)->first();
    }

    public function test_the_profile_needs_a_signed_in_job_seeker(): void
    {
        $this->getJson('/api/v1/me/profile')->assertStatus(401);
        $this->patchJson('/api/v1/me/profile', [])->assertStatus(401);

        $member = $this->account('company_member', 'member@example.test');
        $this->api('GET', '/me/profile', token: $this->tokenFor($member))->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:job-seeker-only');
        $this->api('PATCH', '/me/profile', ['headline' => 'Hello'], $this->tokenFor($member))->assertStatus(403);
        $this->assertSame(0, DB::table('jobseeker_profiles')->count());
    }

    public function test_a_new_account_has_an_empty_profile_and_no_row(): void
    {
        $response = $this->api('GET', '/me/profile')->assertOk();

        foreach (['phone', 'whatsapp', 'birth_date', 'gender', 'marital_status', 'religion_id', 'nationality_id', 'expected_salary', 'expected_salary_period_id', 'experience_level_id',
            'availability', 'available_from', 'province_id', 'city_id', 'subdistrict_id', 'village_id', 'postal_code_id', 'address_line', 'headline', 'summary'] as $field) {
            $response->assertJsonPath("data.{$field}", null);
        }

        $response->assertJsonPath('data.is_discoverable', false)->assertJsonPath('data.resume_discoverable', false)
            ->assertJsonPath('data.completeness.complete', false)->assertJsonPath('data.completeness.missing', ['phone', 'location', 'history', 'resume']);
        $this->assertNull($this->profileRow());
    }

    public function test_the_first_save_creates_the_row_and_a_partial_save_keeps_the_rest(): void
    {
        $this->api('PATCH', '/me/profile', ['headline' => 'Backend developer', 'summary' => 'I like building APIs.', 'gender' => 'female'])->assertOk()
            ->assertJsonPath('data.headline', 'Backend developer')->assertJsonPath('data.gender', 'female');
        $this->assertSame(1, DB::table('jobseeker_profiles')->count());

        $this->api('PATCH', '/me/profile', ['headline' => 'Senior backend developer', 'marital_status' => 'single', 'birth_date' => '1995-04-12'])->assertOk()
            ->assertJsonPath('data.headline', 'Senior backend developer')->assertJsonPath('data.summary', 'I like building APIs.')
            ->assertJsonPath('data.gender', 'female')->assertJsonPath('data.birth_date', '1995-04-12')->assertJsonPath('data.marital_status', 'single');

        $this->api('GET', '/me/profile')->assertOk()->assertJsonPath('data.headline', 'Senior backend developer')->assertJsonPath('data.birth_date', '1995-04-12');
        $this->assertSame(1, DB::table('jobseeker_profiles')->count());
    }

    public function test_an_optional_field_is_cleared_with_null(): void
    {
        $this->api('PATCH', '/me/profile', ['headline' => 'Developer', 'gender' => 'male', 'phone' => '081234567890'])->assertOk();
        $this->api('PATCH', '/me/profile', ['headline' => null, 'gender' => null])->assertOk()->assertJsonPath('data.headline', null)->assertJsonPath('data.gender', null)->assertJsonPath('data.phone', '081234567890');
        $this->assertNull($this->profileRow()->headline);
    }

    public function test_one_account_never_reaches_the_profile_of_another(): void
    {
        $other = $this->account('job_seeker', 'budi@example.test');
        $this->api('PATCH', '/me/profile', ['headline' => 'Mine'])->assertOk();
        $this->api('PATCH', '/me/profile', ['headline' => 'Budi'], $this->tokenFor($other))->assertOk();

        $this->assertSame('Mine', $this->profileRow()->headline);
        $this->assertSame('Budi', $this->profileRow($other)->headline);
        $this->api('GET', '/me/profile', token: $this->tokenFor($other))->assertJsonPath('data.headline', 'Budi');
    }

    public function test_phone_numbers_are_cleaned_and_checked(): void
    {
        $this->api('PATCH', '/me/profile', ['phone' => '+62 812-3456 (7890)', 'whatsapp' => '0812.3456.7890'])->assertOk()
            ->assertJsonPath('data.phone', '+6281234567890')->assertJsonPath('data.whatsapp', '081234567890');

        foreach (['abc', '1234567', '+1234567890123456', '08-12 3x', '++6281234567890', ''] as $bad) {
            if ($bad === '') {
                continue;
            }

            $this->api('PATCH', '/me/profile', ['phone' => $bad])->assertStatus(422);
        }

        $this->assertSame('+6281234567890', $this->profileRow()->phone);
    }

    public function test_dates_text_and_fixed_values_are_checked(): void
    {
        foreach ([
            ['birth_date' => '2999-01-01'], ['birth_date' => '1899-12-31'], ['birth_date' => '12-04-1995'], ['gender' => 'other'], ['marital_status' => 'engaged'],
            ['availability' => 'maybe'], ['headline' => str_repeat('a', 101)], ['summary' => str_repeat('a', 2001)], ['address_line' => str_repeat('a', 256)],
            ['expected_salary' => -1], ['is_discoverable' => 'sure'], ['available_from' => 'soon'],
        ] as $body) {
            $this->api('PATCH', '/me/profile', $body)->assertStatus(422);
        }

        $this->assertNull($this->profileRow());
    }

    public function test_the_job_seeker_must_be_at_least_fifteen_years_old(): void
    {
        Carbon::setTestNow('2026-10-05 09:00:00');

        $this->api('PATCH', '/me/profile', ['birth_date' => '2011-10-06'])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-profile')->assertJsonStructure(['errors' => ['birth_date']]);
        $this->api('PATCH', '/me/profile', ['birth_date' => '2020-01-01'])->assertStatus(422);
        $this->api('PATCH', '/me/profile', ['birth_date' => '2011-10-05'])->assertOk()->assertJsonPath('data.birth_date', '2011-10-05');
        $this->api('PATCH', '/me/profile', ['birth_date' => null])->assertOk()->assertJsonPath('data.birth_date', null);

        // Other edits are not blocked by a birth date that was saved earlier.
        DB::table('jobseeker_profiles')->where('user_id', $this->userId)->update(['birth_date' => '2015-01-01']);
        $this->api('PATCH', '/me/profile', ['headline' => 'Still fine'])->assertOk();

        Carbon::setTestNow();
    }

    public function test_the_job_seeker_must_be_at_most_sixty_years_old(): void
    {
        Carbon::setTestNow('2026-10-05 09:00:00');

        // Born on 5 October 1965 is 61 today; born the next day, still 60.
        $this->api('PATCH', '/me/profile', ['birth_date' => '1965-10-05'])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-profile')->assertJsonStructure(['errors' => ['birth_date']]);
        $this->api('PATCH', '/me/profile', ['birth_date' => '1950-01-01'])->assertStatus(422);
        $this->api('PATCH', '/me/profile', ['birth_date' => '1965-10-06'])->assertOk()->assertJsonPath('data.birth_date', '1965-10-06');

        Carbon::setTestNow();
    }

    public function test_the_age_limits_follow_the_admin_settings(): void
    {
        Carbon::setTestNow('2026-10-05 09:00:00');
        $store = $this->app->make(SettingsStore::class);

        $this->api('PATCH', '/me/profile', ['birth_date' => '2011-10-05'])->assertOk();
        $store->setGlobal(JobSeekerSettings::MIN_AGE, 18, null);
        $store->setGlobal(JobSeekerSettings::MAX_AGE, 70, null);

        $this->api('PATCH', '/me/profile', ['birth_date' => '2011-10-05'])->assertStatus(422);
        $this->api('PATCH', '/me/profile', ['birth_date' => '2008-10-05'])->assertOk();
        $this->api('PATCH', '/me/profile', ['birth_date' => '1956-10-06'])->assertOk();
        $this->api('PATCH', '/me/profile', ['birth_date' => '1955-10-05'])->assertStatus(422);

        Carbon::setTestNow();
    }

    public function test_the_age_is_counted_from_today_in_jakarta(): void
    {
        // 18:00 UTC on 4 October is already 01:00 on 5 October in Jakarta, so the 15th birthday of 5 October 2011 has come.
        Carbon::setTestNow('2026-10-04 18:00:00');

        $this->api('PATCH', '/me/profile', ['birth_date' => '2011-10-05'])->assertOk();
        $this->api('PATCH', '/me/profile', ['birth_date' => '2011-10-06'])->assertStatus(422);

        Carbon::setTestNow();
    }

    public function test_the_available_from_date_is_from_today_up_to_thirty_days_ahead(): void
    {
        Carbon::setTestNow('2026-10-05 09:00:00');

        $this->api('PATCH', '/me/profile', ['available_from' => '2026-10-04'])->assertStatus(422)->assertJsonStructure(['errors' => ['available_from']]);
        $this->api('PATCH', '/me/profile', ['available_from' => '2026-11-05'])->assertStatus(422);
        $this->api('PATCH', '/me/profile', ['available_from' => '2026-10-05'])->assertOk()->assertJsonPath('data.available_from', '2026-10-05');
        $this->api('PATCH', '/me/profile', ['available_from' => '2026-11-04'])->assertOk()->assertJsonPath('data.available_from', '2026-11-04');
        $this->api('PATCH', '/me/profile', ['available_from' => null])->assertOk()->assertJsonPath('data.available_from', null);

        // A date that has passed since it was saved does not block other edits.
        DB::table('jobseeker_profiles')->where('user_id', $this->userId)->update(['available_from' => '2026-01-01']);
        $this->api('PATCH', '/me/profile', ['headline' => 'Still fine'])->assertOk();

        Carbon::setTestNow();
    }

    public function test_the_available_from_limit_follows_the_admin_setting(): void
    {
        Carbon::setTestNow('2026-10-05 09:00:00');

        $this->api('PATCH', '/me/profile', ['available_from' => '2026-11-14'])->assertStatus(422);
        $this->app->make(SettingsStore::class)->setGlobal(JobSeekerSettings::AVAILABLE_FROM_MAX_DAYS, 40, null);
        $this->api('PATCH', '/me/profile', ['available_from' => '2026-11-14'])->assertOk();
        $this->api('PATCH', '/me/profile', ['available_from' => '2026-11-15'])->assertStatus(422);

        Carbon::setTestNow();
    }

    public function test_the_settings_have_their_defaults_and_ranges(): void
    {
        $settings = $this->app->make(Settings::class);
        $this->assertSame(15, $settings->integer(JobSeekerSettings::MIN_AGE));
        $this->assertSame(60, $settings->integer(JobSeekerSettings::MAX_AGE));
        $this->assertSame(30, $settings->integer(JobSeekerSettings::AVAILABLE_FROM_MAX_DAYS));

        $catalog = $this->app->make(SettingsCatalog::class);

        foreach ([
            [JobSeekerSettings::MIN_AGE, 13, 30, [12, 31]],
            [JobSeekerSettings::MAX_AGE, 40, 100, [39, 101]],
            [JobSeekerSettings::AVAILABLE_FROM_MAX_DAYS, 1, 40, [0, 41]],
        ] as [$key, $low, $high, $outside]) {
            $definition = $catalog->find($key);
            $this->assertFalse($definition->overridable);
            $this->assertSame($low, $definition->normalize($low));
            $this->assertSame($high, $definition->normalize($high));

            foreach ($outside as $bad) {
                try {
                    $definition->normalize($bad);
                    $this->fail("{$key}: {$bad} was accepted");
                } catch (BusinessRuleViolation) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    public function test_the_text_limits_are_accepted_at_their_edge(): void
    {
        $this->api('PATCH', '/me/profile', ['headline' => str_repeat('a', 100), 'summary' => str_repeat('b', 2000), 'address_line' => str_repeat('c', 255)])->assertOk();
    }

    public function test_master_values_must_be_active_but_an_old_one_does_not_block_other_edits(): void
    {
        $religion = $this->referenceId('reference_religions', 'islam');
        $nationality = $this->referenceId('reference_nationalities', 'indonesian');
        $level = $this->referenceId('reference_experience_levels', 'five-years');

        $this->api('PATCH', '/me/profile', ['religion_id' => $religion, 'nationality_id' => $nationality, 'experience_level_id' => $level])->assertOk()
            ->assertJsonPath('data.religion_id', $religion)->assertJsonPath('data.nationality_id', $nationality)->assertJsonPath('data.experience_level_id', $level);

        $this->api('PATCH', '/me/profile', ['religion_id' => 999_999])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-profile')->assertJsonStructure(['errors' => ['religion_id']]);

        $inactive = $this->referenceId('reference_nationalities', 'retired', ['is_active' => false]);
        $this->api('PATCH', '/me/profile', ['nationality_id' => $inactive])->assertStatus(422)->assertJsonStructure(['errors' => ['nationality_id']]);

        DB::table('reference_religions')->where('id', $religion)->update(['is_active' => false]);
        $this->api('PATCH', '/me/profile', ['headline' => 'Still fine'])->assertOk()->assertJsonPath('data.religion_id', $religion);
        $this->api('PATCH', '/me/profile', ['religion_id' => null])->assertOk()->assertJsonPath('data.religion_id', null);
    }

    public function test_an_expected_salary_needs_its_period(): void
    {
        $period = $this->referenceId('reference_salary_periods', 'monthly');

        $this->api('PATCH', '/me/profile', ['expected_salary' => 8000000])->assertStatus(422)->assertJsonStructure(['errors' => ['expected_salary_period_id']]);
        $this->api('PATCH', '/me/profile', ['expected_salary' => 8000000, 'expected_salary_period_id' => $period])->assertOk()->assertJsonPath('data.expected_salary', 8000000);
        $this->api('PATCH', '/me/profile', ['expected_salary_period_id' => null])->assertStatus(422);
        $this->api('PATCH', '/me/profile', ['expected_salary' => null, 'expected_salary_period_id' => null])->assertOk()->assertJsonPath('data.expected_salary', null);
        $this->api('PATCH', '/me/profile', ['expected_salary_period_id' => $period])->assertOk();
    }

    public function test_the_location_is_one_matching_chain(): void
    {
        $chain = $this->locationChain();
        $otherProvince = (int) DB::table('reference_provinces')->insertGetId(['bps_code' => '99', 'name' => 'ELSEWHERE']);

        $this->api('PATCH', '/me/profile', [...$chain, 'address_line' => 'Jl. Setiabudi 1'])->assertOk()
            ->assertJsonPath('data.province_id', $chain['province_id'])->assertJsonPath('data.postal_code_id', $chain['postal_code_id']);

        $this->api('PATCH', '/me/profile', ['province_id' => $otherProvince])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-location');
        $this->api('PATCH', '/me/profile', ['province_id' => null])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-location');
        $this->assertSame($chain['province_id'], (int) $this->profileRow()->province_id);

        $this->api('PATCH', '/me/profile', ['province_id' => null, 'city_id' => null, 'subdistrict_id' => null, 'village_id' => null, 'postal_code_id' => null])->assertOk()
            ->assertJsonPath('data.province_id', null)->assertJsonPath('data.address_line', 'Jl. Setiabudi 1');
    }

    public function test_the_two_visibility_switches_start_off_and_are_independent(): void
    {
        $this->api('PATCH', '/me/profile', ['headline' => 'x'])->assertOk()->assertJsonPath('data.is_discoverable', false)->assertJsonPath('data.resume_discoverable', false);
        $this->api('PATCH', '/me/profile', ['is_discoverable' => true])->assertOk()->assertJsonPath('data.is_discoverable', true)->assertJsonPath('data.resume_discoverable', false);
        $this->api('PATCH', '/me/profile', ['resume_discoverable' => true, 'is_discoverable' => false])->assertOk()->assertJsonPath('data.is_discoverable', false)->assertJsonPath('data.resume_discoverable', true);
        $this->api('PATCH', '/me/profile', ['is_discoverable' => null])->assertStatus(422);
    }

    public function test_completeness_follows_the_profile_and_its_lists(): void
    {
        $chain = $this->locationChain();

        $this->api('PATCH', '/me/profile', ['phone' => '081234567890'])->assertOk()->assertJsonPath('data.completeness.missing', ['location', 'history', 'resume']);
        $this->api('PATCH', '/me/profile', ['province_id' => $chain['province_id']])->assertOk()->assertJsonPath('data.completeness.missing', ['location', 'history', 'resume']);
        $this->api('PATCH', '/me/profile', ['city_id' => $chain['city_id']])->assertOk()->assertJsonPath('data.completeness.missing', ['history', 'resume']);

        DB::table('jobseeker_educations')->insert([
            'user_id' => $this->userId, 'education_level_id' => $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5]), 'institution' => 'ITB', 'start_year' => 2014,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->api('GET', '/me/profile')->assertJsonPath('data.completeness.missing', ['resume']);

        DB::table('jobseeker_resumes')->insert(['user_id' => $this->userId, 'name' => 'My CV', 'path' => 'resumes/abc', 'size_bytes' => 1000, 'created_at' => now()]);
        $this->api('GET', '/me/profile')->assertJsonPath('data.completeness.complete', true)->assertJsonPath('data.completeness.missing', []);
    }

    public function test_the_database_guards_the_fixed_values_and_one_profile_per_account(): void
    {
        $row = ['user_id' => 77, 'created_at' => now(), 'updated_at' => now()];
        DB::table('jobseeker_profiles')->insert($row);

        foreach ([['gender' => 'robot'], ['marital_status' => 'engaged'], ['availability' => 'maybe']] as $bad) {
            try {
                DB::transaction(fn () => DB::table('jobseeker_profiles')->insert(['user_id' => 78, 'created_at' => now(), 'updated_at' => now(), ...$bad]));
                $this->fail('A bad value was stored: '.json_encode($bad));
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(QueryException::class);
        DB::table('jobseeker_profiles')->insert($row);
    }

    public function test_the_database_refuses_a_master_that_does_not_exist(): void
    {
        $this->expectException(QueryException::class);
        DB::table('jobseeker_profiles')->insert(['user_id' => 77, 'religion_id' => 999_999, 'created_at' => now(), 'updated_at' => now()]);
    }
}
