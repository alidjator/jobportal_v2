<?php

namespace Tests\Feature\Job;

use Src\Job\Application\JobSettings;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Tests\FeatureTestCase;

final class JobSettingsTest extends FeatureTestCase
{
    public function test_the_defaults_are_no_approval_and_ninety_days(): void
    {
        $settings = $this->app->make(Settings::class);

        $this->assertFalse($settings->boolean(JobSettings::REQUIRE_APPROVAL));
        $this->assertSame(90, $settings->integer(JobSettings::MAX_DEADLINE_DAYS));
        $this->assertFalse($settings->boolean(JobSettings::REQUIRE_APPROVAL, 42));
        $this->assertSame(90, $settings->integer(JobSettings::MAX_DEADLINE_DAYS, 42));
    }

    public function test_both_can_be_overridden_for_one_company_only(): void
    {
        $store = $this->app->make(SettingsStore::class);
        $store->setCompanyOverride(JobSettings::REQUIRE_APPROVAL, 42, true, null);
        $store->setCompanyOverride(JobSettings::MAX_DEADLINE_DAYS, 42, 30, null);

        $settings = $this->app->make(Settings::class);

        $this->assertTrue($settings->boolean(JobSettings::REQUIRE_APPROVAL, 42));
        $this->assertSame(30, $settings->integer(JobSettings::MAX_DEADLINE_DAYS, 42));
        $this->assertFalse($settings->boolean(JobSettings::REQUIRE_APPROVAL, 43));
        $this->assertSame(90, $settings->integer(JobSettings::MAX_DEADLINE_DAYS, 43));
        $this->assertFalse($settings->boolean(JobSettings::REQUIRE_APPROVAL));
    }

    public function test_the_deadline_limit_stays_between_7_and_365_days(): void
    {
        $definition = $this->app->make(SettingsCatalog::class)->find(JobSettings::MAX_DEADLINE_DAYS);

        $this->assertSame(7, $definition->normalize(7));
        $this->assertSame(365, $definition->normalize(365));

        foreach ([6, 366, 0, -1] as $bad) {
            try {
                $definition->normalize($bad);
                $this->fail("{$bad} was accepted");
            } catch (BusinessRuleViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_global_value_applies_to_every_company_without_an_override(): void
    {
        $store = $this->app->make(SettingsStore::class);
        $store->setGlobal(JobSettings::REQUIRE_APPROVAL, true, null);
        $store->setCompanyOverride(JobSettings::REQUIRE_APPROVAL, 42, false, null);

        $settings = $this->app->make(Settings::class);

        $this->assertTrue($settings->boolean(JobSettings::REQUIRE_APPROVAL));
        $this->assertTrue($settings->boolean(JobSettings::REQUIRE_APPROVAL, 43));
        $this->assertFalse($settings->boolean(JobSettings::REQUIRE_APPROVAL, 42), 'a trusted company can be exempted');
    }
}
