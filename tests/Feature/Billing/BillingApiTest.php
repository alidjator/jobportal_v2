<?php

namespace Tests\Feature\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Billing\Application\Contracts\InvoiceBranding;
use Src\Billing\Application\Contracts\InvoicePdfGenerator;
use Src\Identity\Infrastructure\Persistence\UserModel;
use Tests\FeatureTestCase;

class BillingApiTest extends FeatureTestCase
{
    /** @return array{user: UserModel, company: int} */
    private function makeCompany(string $role = 'company'): array
    {
        $uid = DB::table('users')->insertGetId([
            'name' => 'Co '.Str::random(6), 'email' => Str::random(12).'@example.test', 'role' => $role,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $industry = DB::table('industry_types')->insertGetId(['created_at' => now()]);
        $org = DB::table('organization_types')->insertGetId(['created_at' => now()]);
        $company = DB::table('companies')->insertGetId([
            'user_id' => $uid, 'industry_type_id' => $industry, 'organization_type_id' => $org,
            'address' => 'Jl. Test 1', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('contact_infos')->insert(['user_id' => $uid, 'phone' => '0812345', 'created_at' => now(), 'updated_at' => now()]);

        return ['user' => UserModel::findOrFail($uid), 'company' => $company];
    }

    private function makePlan(array $over = []): int
    {
        return DB::table('plans')->insertGetId($over + [
            'label' => 'Plan '.Str::random(10), 'price' => 10, 'job_limit' => 5, 'featured_job_limit' => 2,
            'highlight_job_limit' => 1, 'candidate_cv_view_limit' => 7, 'recommended' => 0, 'frontend_show' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeUserPlan(int $company, int $plan, int $jobLimit = 5): int
    {
        return DB::table('user_plans')->insertGetId([
            'company_id' => $company, 'plan_id' => $plan, 'job_limit' => $jobLimit,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeEarning(int $company, ?int $plan, array $over = []): int
    {
        return DB::table('earnings')->insertGetId($over + [
            'order_id' => 'ORD'.Str::upper(Str::random(8)), 'payment_provider' => 'stripe', 'company_id' => $company,
            'amount' => '25', 'currency_symbol' => '$', 'usd_amount' => '25', 'payment_status' => 'paid',
            'plan_id' => $plan, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_pricing_lists_only_active_plans_publicly(): void
    {
        $shown = $this->makePlan(['recommended' => 1]);
        $hidden = $this->makePlan(['frontend_show' => 0]);

        $data = $this->getJson('/api/pricing')->assertOk()->json('data');
        $ids = array_column($data, 'id');

        $this->assertContains($shown, $ids);
        $this->assertNotContains($hidden, $ids);
        $item = collect($data)->firstWhere('id', $shown);
        $this->assertTrue($item['recommended']);
        $this->assertTrue($item['frontend_show']);
        $this->assertSame(5, $item['job_limit']);
        $this->assertArrayHasKey('candidate_cv_view_limitation', $item);
    }

    public function test_plan_returns_own_user_plan_and_paginated_transactions(): void
    {
        $a = $this->makeCompany();
        $b = $this->makeCompany();
        $plan = $this->makePlan();
        $this->makeUserPlan($a['company'], $plan, 11);
        $this->makeUserPlan($b['company'], $plan, 22);
        $mine = $this->makeEarning($a['company'], $plan);
        $theirs = $this->makeEarning($b['company'], $plan);

        Sanctum::actingAs($a['user']);
        $res = $this->getJson('/api/company/plan')->assertOk();

        $res->assertJsonPath('data.user_plan.company_id', $a['company'])
            ->assertJsonPath('data.user_plan.job_limit', 11)
            ->assertJsonPath('data.user_plan.plan.id', $plan)
            ->assertJsonPath('data.transactions.per_page', 8)
            ->assertJsonPath('data.transactions.total', 1);
        $tx = $res->json('data.transactions.data.0');
        $this->assertSame($mine, $tx['id']);
        $this->assertNotSame($theirs, $tx['id']);
        $this->assertSame(['id', 'label'], array_keys($tx['plan']));
        $this->assertNull($tx['manual_payment']);
    }

    public function test_plan_paginates_by_eight_and_includes_manual_payment(): void
    {
        $a = $this->makeCompany();
        $plan = $this->makePlan();
        $this->makeUserPlan($a['company'], $plan);
        $mp = DB::table('manual_payments')->insertGetId(['type' => 'bank_payment', 'name' => 'Bank X', 'description' => 'd', 'created_at' => now()]);
        for ($i = 0; $i < 9; $i++) {
            $this->makeEarning($a['company'], $plan, $i === 0 ? ['payment_provider' => 'offline', 'manual_payment_id' => $mp, 'created_at' => now()->subDay()] : []);
        }

        Sanctum::actingAs($a['user']);
        $res = $this->getJson('/api/company/plan')->assertOk();
        $this->assertCount(8, $res->json('data.transactions.data'));
        $this->assertSame(9, $res->json('data.transactions.total'));
        $this->assertCount(1, $this->getJson('/api/company/plan?page=2')->json('data.transactions.data'));
        $this->assertSame('Bank X', collect($this->getJson('/api/company/plan?page=2')->json('data.transactions.data'))->first()['manual_payment']['name']);
    }

    public function test_plan_and_plan_limit_404_without_user_plan(): void
    {
        $a = $this->makeCompany();
        Sanctum::actingAs($a['user']);

        $this->getJson('/api/company/plan')->assertNotFound();
        $this->getJson('/api/company/plan-limit')->assertNotFound();
    }

    public function test_plan_limit_is_scoped_to_authenticated_company(): void
    {
        $other = $this->makeCompany();
        $me = $this->makeCompany();
        $plan = $this->makePlan();
        // Rencana perusahaan lain dibuat lebih dulu: versi lama akan membocorkannya.
        $this->makeUserPlan($other['company'], $plan, 99);
        $mine = $this->makeUserPlan($me['company'], $plan, 3);

        Sanctum::actingAs($me['user']);
        $this->getJson('/api/company/plan-limit')->assertOk()
            ->assertJsonPath('data.id', $mine)
            ->assertJsonPath('data.job_limit', 3)
            ->assertJsonPath('data.plan.id', $plan);
    }

    public function test_company_endpoints_require_auth_and_company_role(): void
    {
        $this->getJson('/api/company/plan')->assertUnauthorized();
        $this->getJson('/api/company/plan-limit')->assertUnauthorized();
        $this->getJson('/api/company/download-invoice/1')->assertUnauthorized();

        $candidate = $this->makeCompany('candidate');
        Sanctum::actingAs($candidate['user']);
        $this->getJson('/api/company/plan')->assertUnauthorized();
        $this->getJson('/api/company/plan-limit')->assertUnauthorized();
        $this->getJson('/api/company/download-invoice/1')->assertUnauthorized();
    }

    public function test_download_invoice_returns_pdf_for_owner(): void
    {
        $a = $this->makeCompany();
        $plan = $this->makePlan();
        $id = $this->makeEarning($a['company'], $plan, ['order_id' => 'ORD-PDF1']);

        Sanctum::actingAs($a['user']);
        $res = $this->get('/api/company/download-invoice/'.$id)->assertOk();

        $this->assertStringContainsString('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('invoice_ORD-PDF1.pdf', $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_download_invoice_passes_expected_data_to_generator(): void
    {
        $a = $this->makeCompany();
        $plan = $this->makePlan(['label' => 'Gold '.Str::random(5)]);
        $id = $this->makeEarning($a['company'], $plan, ['order_id' => 'ORD-DATA']);

        $captured = new \ArrayObject;
        $this->app->bind(InvoicePdfGenerator::class, fn () => new class($captured) implements InvoicePdfGenerator
        {
            public function __construct(public \ArrayObject $captured) {}

            public function generate(array $data): string
            {
                $this->captured->exchangeArray($data);

                return '%PDF-fake';
            }
        });
        $this->app->bind(InvoiceBranding::class, fn () => new class implements InvoiceBranding
        {
            public function logo(): ?string
            {
                return 'data:image/png;base64,AAAA';
            }

            public function appName(): string
            {
                return 'TestApp';
            }
        });

        Sanctum::actingAs($a['user']);
        $this->get('/api/company/download-invoice/'.$id)->assertOk();

        $this->assertSame('data:image/png;base64,AAAA', $captured['logo']);
        $this->assertSame($id, $captured['transaction']['id']);
        $this->assertSame('ORD-DATA', $captured['transaction']['order_id']);
        $this->assertSame($plan, $captured['transaction']['plan']['id']);
        $this->assertSame($a['user']->email, $captured['transaction']['company']['email']);
        $this->assertSame('0812345', $captured['transaction']['company']['phone']);
    }

    public function test_invoice_view_renders_expected_content(): void
    {
        $html = view('billing::invoice', [
            'logo' => 'data:image/png;base64,AAAA',
            'appName' => 'TestApp',
            'transaction' => [
                'order_id' => 'ORD-VIEW', 'payment_provider' => 'stripe', 'payment_type' => 'subscription_based',
                'currency_symbol' => '$', 'amount' => '25', 'created_at' => '2024-03-05T10:00:00.000000Z',
                'plan' => ['label' => 'Gold', 'job_limit' => 5, 'featured_job_limit' => 2, 'highlight_job_limit' => 1,
                    'candidate_cv_view_limitation' => 'unlimited', 'candidate_cv_view_limit' => 7],
                'company' => ['name' => 'Acme', 'email' => 'a@b.test', 'phone' => '123', 'address' => 'Street 1'],
            ],
        ])->render();

        foreach (['#ORD-VIEW', 'Mar 05, 2024', 'Gold', 'Stripe', '$25', 'Acme', 'a@b.test', 'Street 1', 'Unlimited', 'data:image/png;base64,AAAA'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function test_download_invoice_of_other_company_or_missing_is_404(): void
    {
        $a = $this->makeCompany();
        $b = $this->makeCompany();
        $plan = $this->makePlan();
        $others = $this->makeEarning($b['company'], $plan);

        Sanctum::actingAs($a['user']);
        $this->getJson('/api/company/download-invoice/'.$others)->assertNotFound()->assertJsonStructure(['error']);
        $this->getJson('/api/company/download-invoice/999999999')->assertNotFound();
    }
}
