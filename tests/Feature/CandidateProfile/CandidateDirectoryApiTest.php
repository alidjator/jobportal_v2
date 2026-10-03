<?php

namespace Tests\Feature\CandidateProfile;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class CandidateDirectoryApiTest extends CandidateTestCase
{
    /** @return array{0:int,1:string,2:int} [candidate id, username, user id] */
    private function listed(array $candidate = [], array $user = []): array
    {
        $u = $this->makeUser();
        if ($user) {
            DB::table('users')->where('id', $u->id)->update($user);
        }
        $id = $this->makeCandidate($u, $candidate);

        return [$id, DB::table('users')->where('id', $u->id)->value('username'), $u->id];
    }

    /** @return list<int> */
    private function ids(string $query = '', array $headers = []): array
    {
        return array_column($this->getJson('/api/candidates?paginate=100&'.$query, $headers)->assertOk()->json('data'), 'id');
    }

    public function test_list_is_public_with_old_item_shape(): void
    {
        $exp = $this->makeExperience('Senior-'.uniqid());
        [$id, $username] = $this->listed(['title' => 'Dev', 'country' => 'ID', 'experience_id' => $exp, 'photo' => 'images/candidates/p.jpg']);

        $item = collect($this->getJson('/api/candidates?paginate=100')->assertOk()->json('data'))->firstWhere('id', $id);

        $this->assertSame(['id', 'experience', 'profession', 'username', 'email', 'role', 'name', 'title', 'country', 'photo_url', 'bookmarked'], array_keys($item));
        $this->assertSame($username, $item['username']);
        $this->assertSame('candidate', $item['role']);
        $this->assertStringStartsWith('Senior-', $item['experience']);
        $this->assertNull($item['profession']);
        $this->assertStringEndsWith('images/candidates/p.jpg', $item['photo_url']);
        $this->assertFalse($item['bookmarked']);
        $this->assertArrayNotHasKey('password', $item);
    }

    public function test_hidden_and_unavailable_candidates_are_excluded_by_default(): void
    {
        [$visible] = $this->listed();
        [$hidden] = $this->listed(['visibility' => 0]);
        [$away] = $this->listed(['status' => 'not_available']);

        $ids = $this->ids();
        $this->assertContains($visible, $ids);
        $this->assertNotContains($hidden, $ids);
        $this->assertNotContains($away, $ids);
        $this->assertContains($away, $this->ids('status=not_available'));
    }

    public function test_company_users_are_never_listed(): void
    {
        $company = $this->makeUser('company');
        $id = $this->makeCandidate($company);

        $this->assertNotContains($id, $this->ids());
    }

    public function test_filters(): void
    {
        $exp = $this->makeExperience($expName = 'ExpName-'.uniqid());
        $edu = $this->makeEducation($eduName = 'EduName-'.uniqid());
        $skill = $this->makeSkill('S-'.uniqid());
        $lang = $this->makeLanguage('Lang-'.uniqid());
        $keyword = 'Zxq'.uniqid();

        [$match, , $matchUser] = $this->listed(['gender' => 'female', 'experience_id' => $exp, 'education_id' => $edu], ['name' => "$keyword Smith"]);
        [$other] = $this->listed(['gender' => 'male']);
        DB::table('candidate_skill')->insert(['candidate_id' => $match, 'skill_id' => $skill]);
        DB::table('candidate_language')->insert(['candidate_id' => $match, 'candidate_language_id' => $lang]);

        $this->assertSame([$match], $this->ids("keyword=$keyword"));
        $this->assertSame([$match], $this->ids("keyword=$keyword&gender=female"));
        $this->assertSame([], $this->ids("keyword=$keyword&gender=male"));
        $this->assertSame([$match], $this->ids('experience='.urlencode($expName)));
        $this->assertSame([$match], $this->ids('education='.urlencode($eduName)));
        $this->assertContains($match, $this->ids('experience=all&education=all'));
        $this->assertSame([$match], $this->ids("skills[]=$skill"));
        $this->assertSame([$match], $this->ids("language=$lang"));
        $this->assertNotContains($other, $this->ids('gender=female'));
        $this->assertSame($matchUser, DB::table('candidates')->where('id', $match)->value('user_id'));
    }

    public function test_profession_filter(): void
    {
        $profession = DB::table('professions')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        [$match] = $this->listed(['profession_id' => $profession]);
        [$other] = $this->listed();

        $this->assertSame([$match], $this->ids("profession=$profession"));
        $this->assertNotContains($other, $this->ids("profession=$profession"));
    }

    public function test_location_filter_uses_radius_and_keeps_other_filters(): void
    {
        [$near] = $this->listed(['lat' => -6.2, 'long' => 106.8, 'gender' => 'female']);
        [$nearMale] = $this->listed(['lat' => -6.21, 'long' => 106.81, 'gender' => 'male']);
        [$far] = $this->listed(['lat' => 1.35, 'long' => 103.8, 'gender' => 'female']);

        $ids = $this->ids('lat=-6.2&long=106.8&gender=female');
        $this->assertContains($near, $ids);
        $this->assertNotContains($nearMale, $ids);
        $this->assertNotContains($far, $ids);

        $this->getJson('/api/candidates?lat=abc&long=1')->assertUnprocessable()->assertJsonValidationErrors('lat');
    }

    public function test_sortby_and_pagination(): void
    {
        [$old] = $this->listed(['created_at' => '2001-01-01 00:00:00', 'country' => $c = 'Sort'.uniqid()]);
        [$new] = $this->listed(['created_at' => '2002-01-01 00:00:00', 'country' => $c]);

        $latest = array_values(array_intersect($this->ids('sortby=latest'), [$old, $new]));
        $oldest = array_values(array_intersect($this->ids('sortby=oldest'), [$old, $new]));
        $this->assertSame([$new, $old], $latest);
        $this->assertSame([$old, $new], $oldest);

        $this->assertCount(1, $this->getJson('/api/candidates?paginate=1')->assertOk()->json('data'));
        $this->getJson('/api/candidates?paginate=1000')->assertUnprocessable();
    }

    public function test_logged_in_candidate_is_rejected_and_company_sees_bookmarks(): void
    {
        Sanctum::actingAs($this->makeUser());
        $this->getJson('/api/candidates')->assertUnauthorized()->assertExactJson(['error' => 'Unauthorized Access']);

        [$bookmarked] = $this->listed();
        [$plain] = $this->listed();
        $companyUser = $this->makeUser('company');
        $companyId = DB::table('companies')->insertGetId([
            'user_id' => $companyUser->id,
            'industry_type_id' => DB::table('industry_types')->insertGetId([]),
            'organization_type_id' => DB::table('organization_types')->insertGetId([]),
            'team_size_id' => DB::table('team_sizes')->insertGetId([]),
        ]);
        DB::table('bookmark_company')->insert(['company_id' => $companyId, 'candidate_id' => $bookmarked]);

        Sanctum::actingAs($companyUser);
        $items = collect($this->getJson('/api/candidates?paginate=100')->assertOk()->json('data'));
        $this->assertTrue($items->firstWhere('id', $bookmarked)['bookmarked']);
        $this->assertFalse($items->firstWhere('id', $plain)['bookmarked']);
    }

    public function test_detail_is_public_and_hides_sensitive_columns(): void
    {
        $exp = $this->makeExperience('Mid-'.uniqid());
        $skillName = 'Php-'.uniqid();
        $skill = $this->makeSkill($skillName);
        $lang = $this->makeLanguage($langName = 'Lang-'.uniqid());
        [$id, $username, $userId] = $this->listed([
            'title' => 'Dev', 'experience_id' => $exp, 'birth_date' => '1990-02-03', 'country' => 'Indonesia',
            'expected_salary' => 99999999, 'campaign_channel' => 'secret-campaign',
        ], ['firebase_uid' => 'fb-secret', 'google_id' => 'g-secret', 'email_verified_at' => now()]);
        DB::table('candidate_skill')->insert(['candidate_id' => $id, 'skill_id' => $skill]);
        DB::table('candidate_language')->insert(['candidate_id' => $id, 'candidate_language_id' => $lang]);
        DB::table('contact_infos')->insert(['user_id' => $userId, 'phone' => '0812', 'email' => 'c@example.test']);
        DB::table('social_links')->insert(['user_id' => $userId, 'social_media' => 'github', 'url' => 'https://github.com/x']);
        DB::table('candidate_experiences')->insert(['candidate_id' => $id, 'company' => 'ACME', 'start' => '2020-01-01', 'end' => null]);
        DB::table('candidate_education')->insert(['candidate_id' => $id, 'year' => '2012', 'name' => 'Uni']);

        $response = $this->getJson("/api/candidates/$username")->assertOk();
        $data = $response->json('data');

        $this->assertSame($skillName, $data['skills']);
        $this->assertSame($langName, $data['languages']);
        $this->assertSame($username, $data['candidate']['username']);
        $this->assertSame('03 February, 1990', $data['candidate']['candidate']['birth_date']);
        $this->assertSame('Indonesia', $data['candidate']['candidate']['full_address']);
        $this->assertSame('0812', $data['candidate']['contact_info']['phone']);
        $this->assertSame('github', $data['candidate']['social_info'][0]['social_media']);
        $this->assertSame('ACME', $data['candidate']['candidate']['experiences'][0]['company']);
        $this->assertSame('01 Jan 2020', $data['candidate']['candidate']['experiences'][0]['formatted_start']);
        $this->assertNull($data['candidate']['candidate']['experiences'][0]['formatted_end']);
        $this->assertSame('Uni', $data['candidate']['candidate']['educations'][0]['name']);
        $this->assertSame($exp, $data['candidate']['candidate']['experience']['id']);

        $json = $response->getContent();
        foreach (['password', 'fb-secret', 'g-secret', 'email_verified_at', 'secret-campaign', 'expected_salary', 'remember_token', 'firebase_uid'] as $leak) {
            $this->assertStringNotContainsString($leak, $json);
        }
    }

    public function test_detail_not_found_cases(): void
    {
        $this->getJson('/api/candidates/'.uniqid('nobody'))->assertNotFound()->assertJsonStructure(['error']);

        [, $hiddenUsername] = $this->listed(['visibility' => 0]);
        $this->getJson("/api/candidates/$hiddenUsername")->assertNotFound();

        $company = $this->makeUser('company');
        $this->getJson("/api/candidates/{$company->username}")->assertNotFound();

        $noRow = $this->makeUser();
        $this->getJson("/api/candidates/{$noRow->username}")->assertNotFound();
    }
}
