<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\LegacyImport\Application\ImportReport;
use Src\LegacyImport\Application\ImportRunner;
use Tests\Concerns\CreatesReferenceRows;
use Tests\Concerns\UsesLegacyDatabase;
use Tests\FeatureTestCase;

final class ProfilesImporterTest extends FeatureTestCase
{
    use CreatesReferenceRows;
    use UsesLegacyDatabase;

    private int $islam;

    private int $twoYears;

    private int $monthly;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 09:00:00');
        $this->setUpLegacyDatabase();

        $this->islam = $this->referenceId('reference_religions', 'islam');
        $this->twoYears = $this->referenceId('reference_experience_levels', 'two-years');
        $this->monthly = $this->referenceId('reference_salary_periods', 'monthly');
        $this->legacyMasterItem('religions', 1, ['en' => 'Islam']);
        $this->legacyMasterItem('experiences', 1, ['en' => 'Two Years']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function import(bool $write = true): ImportReport
    {
        $runner = $this->app->make(ImportRunner::class);

        foreach (['masters', 'accounts'] as $entity) {
            $runner->run($entity, true);
        }

        return $runner->run('profiles', $write)['report'];
    }

    /** @return array<string, mixed> */
    private function entity(ImportReport $report): array
    {
        return $report->toArray(true)['entities']['profiles'];
    }

    private function profile(): object
    {
        return DB::table('jobseeker_profiles')->first();
    }

    /** A legacy job seeker with a candidate row. @param  array<string, mixed>  $candidate */
    private function seeker(array $candidate = [], array $user = []): int
    {
        return $this->legacyUser($user, $candidate);
    }

    // ----- what is imported and how -----

    public function test_a_profile_is_imported_with_its_fields_for_an_imported_job_seeker(): void
    {
        $chain = $this->locationChain();
        $user = $this->seeker([
            'title' => 'Backend developer', 'gender' => 'female', 'bio' => '<p>I build <b>APIs</b>.</p>', 'marital_status' => 'Menikah', 'birth_date' => '1998-05-10',
            'phone' => '0812-3456-7890', 'whatsapp_number' => '+62 812 3456 7891', 'expected_salary' => 8_000_000, 'address' => 'Jl. Merdeka 1', 'religion_id' => 1, 'experience_id' => 1,
            'region' => '32', 'district' => '3273', 'subdistrict' => 327301, 'kelurahan' => 3273011001, 'postcode' => '40153', 'status' => 'available',
            'created_at' => '2023-01-02 03:04:05', 'updated_at' => '2023-02-03 04:05:06',
        ]);

        $report = $this->import();

        $profile = $this->profile();
        $newUser = (int) DB::table('legacyimport_id_map')->where('entity', 'user')->where('legacy_id', $user)->value('new_id');
        $this->assertSame($newUser, (int) $profile->user_id);
        $this->assertSame('Backend developer', $profile->headline);
        $this->assertSame('I build APIs.', $profile->summary);
        $this->assertSame('female', $profile->gender);
        $this->assertSame('married', $profile->marital_status);
        $this->assertSame('1998-05-10', $profile->birth_date);
        $this->assertSame('08123456789'.'0', $profile->phone);
        $this->assertSame('+6281234567891', $profile->whatsapp);
        $this->assertSame(8_000_000, (int) $profile->expected_salary);
        $this->assertSame($this->monthly, (int) $profile->expected_salary_period_id);
        $this->assertSame($this->islam, (int) $profile->religion_id);
        $this->assertSame($this->twoYears, (int) $profile->experience_level_id);
        $this->assertSame('open_to_work', $profile->availability);
        $this->assertSame('Jl. Merdeka 1', $profile->address_line);
        $this->assertSame([$chain['province_id'], $chain['city_id'], $chain['subdistrict_id'], $chain['village_id'], $chain['postal_code_id']], [(int) $profile->province_id, (int) $profile->city_id, (int) $profile->subdistrict_id, (int) $profile->village_id, (int) $profile->postal_code_id]);
        $this->assertSame([0, 0], [(int) $profile->is_discoverable, (int) $profile->resume_discoverable]);
        $this->assertNull($profile->nationality_id);
        $this->assertSame('2023-01-02 03:04:05', $profile->created_at);
        $this->assertSame('2023-02-03 04:05:06', $profile->updated_at);
        $this->assertSame((int) $profile->id, (int) DB::table('legacyimport_id_map')->where('entity', 'profile')->value('new_id'));
        $this->assertSame(1, $this->entity($report)['imported']);
    }

    public function test_values_the_columns_can_hold_are_kept_as_they_are_and_the_others_emptied_with_a_note(): void
    {
        $this->seeker([
            'phone' => '12345', 'whatsapp_number' => 'call me', 'birth_date' => '2020-01-01', 'marital_status' => 'it is complicated', 'expected_salary' => 500,
            'gender' => 'other', 'status' => 'available_in', 'available_in' => '2026-01-01',
        ]);

        $report = $this->entity($this->import());

        $profile = $this->profile();
        $this->assertSame([null, null, '2020-01-01', null, 500, null, '2026-01-01'], [$profile->phone, $profile->whatsapp, $profile->birth_date, $profile->marital_status, (int) $profile->expected_salary, $profile->gender, $profile->available_from]);
        $this->assertSame('open_to_work', $profile->availability);
        $this->assertSame(
            ['marital-status-unknown' => 1, 'phone-invalid' => 2],
            collect($report['notes'])->sortKeys()->all(),
        );
        $this->assertSame(1, $report['imported']);
    }

    public function test_a_birth_date_is_kept_whatever_the_age(): void
    {
        $this->seeker(['birth_date' => '1950-01-01'], ['email' => 'old@example.test']);
        $this->seeker(['birth_date' => '1990-01-01'], ['email' => 'ok@example.test']);

        $this->import();

        $this->assertSame(['1950-01-01', '1990-01-01'], DB::table('jobseeker_profiles')->orderBy('id')->pluck('birth_date')->all());
    }

    public function test_availability_is_mapped_and_a_future_date_is_kept_whatever_the_window(): void
    {
        $this->seeker(['status' => 'available'], ['email' => 'a@example.test']);
        $this->seeker(['status' => 'not_available'], ['email' => 'b@example.test']);
        $this->seeker(['status' => 'available_in', 'available_in' => '2026-10-20'], ['email' => 'c@example.test']);
        $this->seeker(['status' => 'available_in', 'available_in' => '2026-12-31'], ['email' => 'd@example.test']);
        $this->seeker(['status' => null], ['email' => 'e@example.test']);

        $this->import();

        $rows = DB::table('jobseeker_profiles')->orderBy('id')->get(['availability', 'available_from'])->map(fn ($r): array => [$r->availability, $r->available_from])->all();
        $this->assertSame([['open_to_work', null], ['not_looking', null], ['open_to_work', '2026-10-20'], ['open_to_work', '2026-12-31'], [null, null]], $rows);
    }

    public function test_the_location_is_cut_where_the_chain_stops_being_valid(): void
    {
        $chain = $this->locationChain();
        $this->seeker(['region' => '32', 'district' => '9999', 'subdistrict' => 327301], ['email' => 'a@example.test']);

        $this->import();

        $profile = $this->profile();
        $this->assertSame([$chain['province_id'], null, null], [(int) $profile->province_id, $profile->city_id, $profile->subdistrict_id]);
    }

    public function test_text_is_cut_to_the_limits(): void
    {
        $this->seeker(['title' => str_repeat('t', 150), 'bio' => '<p>'.str_repeat('b', 3000).'</p>']);

        $this->import();

        $this->assertSame([100, 2000], [mb_strlen($this->profile()->headline), mb_strlen($this->profile()->summary)]);
    }

    // ----- what is skipped -----

    public function test_a_candidate_whose_account_was_not_imported_is_skipped(): void
    {
        $demo = $this->seeker([], ['is_demo_field' => 1]);
        $this->seeker([], ['email' => 'ok@example.test']);

        $report = $this->entity($this->import());

        $this->assertSame([['legacy_id' => $this->legacyCandidateId($demo), 'reason' => 'user-not-imported']], $report['skipped_rows']);
        $this->assertSame(1, DB::table('jobseeker_profiles')->count());
    }

    public function test_a_second_candidate_row_of_the_same_account_is_a_duplicate(): void
    {
        $user = $this->seeker();
        $second = $this->legacyRow('candidates', ['user_id' => $user]);

        $report = $this->entity($this->import());

        $this->assertSame([['legacy_id' => $second, 'reason' => 'duplicate-profile']], $report['skipped_rows']);
        $this->assertSame(1, DB::table('jobseeker_profiles')->count());
    }

    // ----- safe to repeat, and a dry run -----

    public function test_running_it_again_adds_nothing_and_never_overwrites(): void
    {
        $this->seeker(['title' => 'First'], ['email' => 'a@example.test']);
        $this->import();
        DB::table('jobseeker_profiles')->update(['headline' => 'Edited in the new system']);
        $this->seeker(['title' => 'Second'], ['email' => 'b@example.test']);

        $report = $this->entity($this->import());

        $this->assertSame(['Edited in the new system', 'Second'], DB::table('jobseeker_profiles')->orderBy('id')->pluck('headline')->all());
        $this->assertSame(1, $report['notes']['already-imported']);
    }

    public function test_a_dry_run_reports_but_writes_nothing(): void
    {
        $this->seeker();

        $report = $this->entity($this->import(false));

        $this->assertSame(['read' => 1, 'imported' => 1, 'skipped' => 0], array_intersect_key($report, array_flip(['read', 'imported', 'skipped'])));
        $this->assertSame(0, DB::table('jobseeker_profiles')->count());
        $this->assertSame(0, DB::table('legacyimport_id_map')->where('entity', 'profile')->count());
    }

    // ----- the result seen through the API -----

    public function test_the_job_seeker_sees_the_imported_profile_through_the_api_and_it_is_not_discoverable(): void
    {
        $this->seeker(['title' => 'Backend developer', 'phone' => '081234567890'], ['email' => 'siti@example.test', 'password' => Hash::make('secret123'), 'email_verified_at' => '2024-03-01 09:00:00']);
        $this->import();

        $user = UserModel::query()->where('email', 'siti@example.test')->firstOrFail();
        $token = $user->createToken('device', ['*'], now()->addDay())->plainTextToken;

        $profile = $this->withToken($token)->getJson('/api/v1/me/profile');

        // The terms gate applies to every migrated account until it accepts them; no document is published in this test, so nothing is required.
        $profile->assertOk()->assertJsonPath('data.headline', 'Backend developer')->assertJsonPath('data.phone', '081234567890')
            ->assertJsonPath('data.is_discoverable', false)->assertJsonPath('data.resume_discoverable', false);
    }
}
