<?php

namespace Tests\Feature\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Identity\Infrastructure\Persistence\UserModel;
use Tests\FeatureTestCase;

class CrossContextTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // tabel lama punya banyak kolom NOT NULL tanpa default; cukup untuk seed minimal di sesi tes ini
        DB::statement("SET SESSION sql_mode=''");
    }

    private function candidateUser(string $password = 'secret-pass-1'): array
    {
        $email = Str::random(10).'@example.test';
        $userId = DB::table('users')->insertGetId([
            'name' => 'Cand '.Str::random(4), 'username' => Str::random(10), 'email' => $email,
            'password' => bcrypt($password), 'role' => 'candidate', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('candidates')->insert(['user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);

        return [$userId, $email, $password];
    }

    public function test_login_returns_candidate_profile_from_projection(): void
    {
        [$id, $email, $password] = $this->candidateUser();

        $res = $this->postJson('/api/login', compact('email', 'password'))->assertOk();

        $this->assertNotEmpty($res->json('data.token'));
        $this->assertNotNull($res->json('data.user'), 'CandidateProfileProjection must be tagged and resolved by Identity');
    }

    public function test_company_login_returns_company_profile_from_projection(): void
    {
        $email = Str::random(10).'@example.test';
        $userId = DB::table('users')->insertGetId([
            'name' => 'Co '.Str::random(4), 'username' => Str::random(10), 'email' => $email,
            'password' => bcrypt('secret-pass-1'), 'role' => 'company', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('companies')->insert([
            'user_id' => $userId,
            'industry_type_id' => DB::table('industry_types')->insertGetId(['created_at' => now(), 'updated_at' => now()]),
            'organization_type_id' => DB::table('organization_types')->insertGetId(['created_at' => now(), 'updated_at' => now()]),
            'team_size_id' => DB::table('team_sizes')->insertGetId(['created_at' => now(), 'updated_at' => now()]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $res = $this->postJson('/api/login', ['email' => $email, 'password' => 'secret-pass-1'])->assertOk();

        $this->assertNotNull($res->json('data.user'));
    }

    public function test_token_created_by_old_app_still_authenticates(): void
    {
        [$userId] = $this->candidateUser();
        $plain = Str::random(40);
        $tokenId = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => 'App\\Models\\User', 'tokenable_id' => $userId, 'name' => 'job-pilot',
            'token' => hash('sha256', $plain), 'abilities' => '["*"]', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson('/api/profile', ['Authorization' => "Bearer {$tokenId}|{$plain}"])
            ->assertOk()->assertJsonPath('data.id', $userId);
    }

    public function test_candidate_cannot_use_company_routes(): void
    {
        [$userId] = $this->candidateUser();
        $token = UserModel::find($userId)->createToken('t')->plainTextToken;

        $this->getJson('/api/company/dashboard', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    }

    public function test_notifications_keep_old_envelope(): void
    {
        [$userId] = $this->candidateUser();
        $token = UserModel::find($userId)->createToken('t')->plainTextToken;

        $this->getJson('/api/notifications', ['Authorization' => "Bearer {$token}"])
            ->assertOk()->assertJsonStructure(['data' => ['current_page', 'data', 'total']]);
    }
}
