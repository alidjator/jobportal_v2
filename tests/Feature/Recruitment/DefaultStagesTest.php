<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class DefaultStagesTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const array DEFAULT_ORDER = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'];

    private function createCompanyAsAdmin(): TestResponse
    {
        config(['app.invitation_url' => 'https://careers.test/invitations/{token}']);
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');

        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo(['companies.manage']);
        $token = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;

        return $this->withToken($token)->postJson('/api/v1/admin/companies', [
            'name' => 'PT Maju Bersama',
            'industry_id' => $this->industryId(),
            'organization_type_id' => $this->organizationTypeId(),
            'admin_email' => 'owner@example.test',
        ]);
    }

    public function test_a_new_company_gets_the_seven_default_stages_in_order(): void
    {
        $this->createCompanyAsAdmin()->assertCreated();

        $companyId = (int) DB::table('company_companies')->value('id');
        $stages = DB::table('recruitment_stages')->where('company_id', $companyId)->orderBy('position')->get();

        $this->assertSame(self::DEFAULT_ORDER, $stages->pluck('type')->all());
        $this->assertSame(['Applied', 'Screening', 'Interview', 'Offer', 'Hired', 'Rejected', 'Withdrawn'], $stages->pluck('name')->all());
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $stages->pluck('position')->map(fn ($position) => (int) $position)->all());
        $this->assertSame([1], $stages->pluck('is_default')->map(fn ($flag) => (int) $flag)->unique()->all());
    }

    public function test_a_company_is_not_created_when_its_stages_cannot_be_written(): void
    {
        $this->app->instance(DefaultStageProvisioner::class, new class implements DefaultStageProvisioner
        {
            public function provision(int $companyId): void
            {
                throw new RuntimeException('boom');
            }
        });

        $this->createCompanyAsAdmin()->assertStatus(500);

        $this->assertSame(0, DB::table('company_companies')->count());
        $this->assertSame(0, DB::table('company_invitations')->count());
    }

    public function test_provisioning_twice_adds_nothing(): void
    {
        $companyId = $this->companyRowId();
        $provisioner = $this->app->make(DefaultStageProvisioner::class);

        $provisioner->provision($companyId);
        $provisioner->provision($companyId);

        $this->assertSame(7, DB::table('recruitment_stages')->where('company_id', $companyId)->count());
    }

    public function test_the_command_fills_only_the_companies_without_stages(): void
    {
        $first = $this->companyRowId('pt-satu');
        $second = $this->companyRowId('pt-dua');
        $this->app->make(DefaultStageProvisioner::class)->provision($first);
        DB::table('recruitment_stages')->where('company_id', $first)->where('type', 'screening')->update(['name' => 'Phone Screen']);

        $this->artisan('recruitment:provision-default-stages')->expectsOutput('Checked 2 companies.')->assertSuccessful();

        $this->assertSame(7, DB::table('recruitment_stages')->where('company_id', $first)->count());
        $this->assertSame('Phone Screen', DB::table('recruitment_stages')->where('company_id', $first)->where('type', 'screening')->value('name'));
        $this->assertSame(self::DEFAULT_ORDER, DB::table('recruitment_stages')->where('company_id', $second)->orderBy('position')->pluck('type')->all());
    }

    public function test_the_database_allows_one_default_per_type_and_company(): void
    {
        $companyId = $this->companyRowId();
        $this->app->make(DefaultStageProvisioner::class)->provision($companyId);

        $this->expectException(QueryException::class);
        $this->insertStage($companyId, 'interview', 'Second Default', 8, true);
    }

    public function test_extra_stages_of_the_same_type_are_allowed_but_names_are_unique_per_company_ignoring_case(): void
    {
        $companyId = $this->companyRowId();
        $other = $this->companyRowId('pt-lain');
        $this->app->make(DefaultStageProvisioner::class)->provision($companyId);
        $this->app->make(DefaultStageProvisioner::class)->provision($other);

        $this->insertStage($companyId, 'interview', 'Technical Interview', 8, false);
        $this->insertStage($companyId, 'interview', 'HR Interview', 9, false);
        $this->insertStage($other, 'interview', 'technical interview', 8, false);
        $this->assertSame(9, DB::table('recruitment_stages')->where('company_id', $companyId)->count());

        $this->expectException(QueryException::class);
        $this->insertStage($companyId, 'offer', 'TECHNICAL INTERVIEW', 10, false);
    }

    public function test_the_database_refuses_an_unknown_type(): void
    {
        $this->expectException(QueryException::class);
        $this->insertStage($this->companyRowId(), 'assessment', 'Assessment', 1, false);
    }

    public function test_the_stage_type_rules(): void
    {
        $this->assertSame(self::DEFAULT_ORDER, array_map(fn (StageType $type) => $type->value, StageType::cases()));
        $this->assertSame(['rejected', 'withdrawn'], array_values(array_map(
            fn (StageType $type) => $type->value, array_filter(StageType::cases(), fn (StageType $type) => $type->isTerminal()),
        )));
        $this->assertSame(['screening', 'interview', 'offer'], array_values(array_map(
            fn (StageType $type) => $type->value, array_filter(StageType::cases(), fn (StageType $type) => $type->allowsExtraStages()),
        )));
    }

    public function test_the_stage_limit_defaults_to_ten_and_can_be_overridden_per_company(): void
    {
        $settings = $this->app->make(Settings::class);
        $this->assertSame(10, $settings->integer(RecruitmentSettings::MAX_STAGES));

        $this->app->make(SettingsStore::class)->setCompanyOverride(RecruitmentSettings::MAX_STAGES, 42, 15, null);

        $settings = $this->app->make(Settings::class);
        $this->assertSame(15, $settings->integer(RecruitmentSettings::MAX_STAGES, 42));
        $this->assertSame(10, $settings->integer(RecruitmentSettings::MAX_STAGES, 43));
    }

    public function test_the_stage_limit_stays_between_7_and_20(): void
    {
        $definition = $this->app->make(SettingsCatalog::class)->find(RecruitmentSettings::MAX_STAGES);

        $this->assertSame(7, $definition->normalize(7));
        $this->assertSame(20, $definition->normalize(20));

        foreach ([6, 21, 0, -1] as $bad) {
            try {
                $definition->normalize($bad);
                $this->fail("{$bad} was accepted");
            } catch (BusinessRuleViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function insertStage(int $companyId, string $type, string $name, int $position, bool $isDefault): void
    {
        DB::table('recruitment_stages')->insert([
            'company_id' => $companyId, 'type' => $type, 'name' => $name, 'position' => $position, 'is_default' => $isDefault,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
