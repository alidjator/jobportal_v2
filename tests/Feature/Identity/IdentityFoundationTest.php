<?php

namespace Tests\Feature\Identity;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Src\Identity\Domain\AccountStatus;
use Src\Identity\Domain\Admin;
use Src\Identity\Domain\AdminRepository;
use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\UserType;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Shared\Infrastructure\ContextServiceProvider;
use Tests\FeatureTestCase;

final class IdentityFoundationTest extends FeatureTestCase
{
    private const string PREFIX = ContextServiceProvider::API_PREFIX.'/probe';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:user'])->get(self::PREFIX.'/user', fn (Request $request) => ['id' => $request->user()->getKey()]);
        Route::middleware(['api', 'auth:admin'])->get(self::PREFIX.'/admin', fn (Request $request) => ['id' => $request->user()->getKey()]);
    }

    private function newUser(string $email = 'siti@example.test'): User
    {
        return new User(null, UserType::JobSeeker, 'Siti', $email, 'hash', null, AccountStatus::Active, null, 'id', 'campaign-1', null, null, null);
    }

    public function test_a_user_survives_a_round_trip_through_the_repository(): void
    {
        $repository = $this->app->make(UserRepository::class);

        $saved = $repository->save($this->newUser());
        $this->assertNotNull($saved->id);

        $saved->recordSignIn(new \DateTimeImmutable('2026-10-03 09:00:00'));
        $saved->verifyEmail(new \DateTimeImmutable('2026-10-03 09:01:00'));
        $repository->save($saved);

        $loaded = $repository->findByEmail('siti@example.test');

        $this->assertSame($saved->id, $loaded?->id);
        $this->assertSame(UserType::JobSeeker, $loaded->type);
        $this->assertSame('campaign-1', $loaded->registrationCampaignCode());
        $this->assertTrue($loaded->isEmailVerified());
        $this->assertSame('2026-10-03 09:00:00', $loaded->lastLoginAt()?->format('Y-m-d H:i:s'));
        $this->assertNull($repository->findById(0));
    }

    public function test_an_admin_survives_a_round_trip_through_the_repository(): void
    {
        $repository = $this->app->make(AdminRepository::class);

        $saved = $repository->save(new Admin(null, 'Root', 'root@example.test', 'hash', null, AccountStatus::Active, null, null));

        $loaded = $repository->findById((int) $saved->id);

        $this->assertSame('root@example.test', $loaded?->email());
        $this->assertSame(AccountStatus::Active, $loaded->status());
        $this->assertNull($repository->findByEmail('nobody@example.test'));
    }

    public function test_a_user_token_authenticates_on_the_user_guard_only(): void
    {
        $user = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'id']);
        $token = $user->createToken('device', ['*'], now()->addDays(30))->plainTextToken;

        $this->withToken($token)->getJson(self::PREFIX.'/user')->assertOk()->assertJson(['id' => $user->id]);
        $this->withToken($token)->getJson(self::PREFIX.'/admin')->assertStatus(401)->assertJson(['type' => 'urn:jobportal:error:unauthenticated']);
    }

    public function test_an_admin_token_authenticates_on_the_admin_guard_only(): void
    {
        $admin = AdminModel::query()->create(['name' => 'Root', 'email' => 'root@example.test', 'password' => 'hash', 'status' => 'active']);
        $token = $admin->createToken('console', ['*'], now()->addHours(12))->plainTextToken;

        $this->withToken($token)->getJson(self::PREFIX.'/admin')->assertOk()->assertJson(['id' => $admin->id]);
        $this->withToken($token)->getJson(self::PREFIX.'/user')->assertStatus(401);
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $user = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'id']);
        $token = $user->createToken('device', ['*'], now()->subMinute())->plainTextToken;

        $this->withToken($token)->getJson(self::PREFIX.'/user')->assertStatus(401);
    }

    public function test_a_request_without_a_token_is_a_401_problem(): void
    {
        $this->getJson(self::PREFIX.'/user')
            ->assertStatus(401)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_an_admin_holds_roles_and_permissions_of_the_admin_guard(): void
    {
        $admin = AdminModel::query()->create(['name' => 'Root', 'email' => 'root@example.test', 'password' => 'hash', 'status' => 'active']);
        $role = Role::create(['name' => 'auditor', 'guard_name' => AdminModel::GUARD]);
        $role->givePermissionTo(Permission::create(['name' => 'audit_logs.view', 'guard_name' => AdminModel::GUARD]));

        $admin->assignRole('auditor');

        $this->assertTrue($admin->hasPermissionTo('audit_logs.view'));
    }
}
