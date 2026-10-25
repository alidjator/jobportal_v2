<?php

namespace Tests\Unit\Platform;

use PHPUnit\Framework\TestCase;
use Src\Platform\Application\Portal\PortalHealth;
use Src\Platform\Application\Portal\PortalIdentity;
use Src\Platform\Application\Portal\PortalSkippedCompany;
use Src\Shared\Application\Portal\PortalBrandValues;

final class PortalValuesTest extends TestCase
{
    public function test_a_blank_brand_text_becomes_null_and_the_preset_stays_in_range(): void
    {
        $brand = PortalBrandValues::fromArray(['name' => '  Karier Kami ', 'tagline_id' => '   ', 'welcome_en' => '', 'preset' => 99]);

        $this->assertSame('Karier Kami', $brand->name);
        $this->assertNull($brand->taglineId);
        $this->assertNull($brand->welcomeEn);
        $this->assertSame(PortalBrandValues::PRESETS, $brand->preset);
        $this->assertSame(1, PortalBrandValues::fromArray(['preset' => 0])->preset);
        $this->assertTrue(PortalBrandValues::default()->equals(PortalBrandValues::fromArray([])));
    }

    public function test_the_appearance_settings_fall_back_to_their_defaults_when_unknown(): void
    {
        $brand = PortalBrandValues::fromArray(['theme_mode' => 'sepia', 'width' => 'wide', 'contrast' => 'maybe', 'header_color' => '1']);

        $this->assertSame('light', $brand->themeMode);
        $this->assertSame('container', $brand->width);
        $this->assertFalse($brand->contrast);
        $this->assertTrue($brand->headerColor);

        $chosen = PortalBrandValues::fromArray(['theme_mode' => 'system', 'width' => 'full', 'contrast' => true]);

        $this->assertSame(['system', 'full', true], [$chosen->themeMode, $chosen->width, $chosen->contrast]);
        $this->assertFalse(PortalBrandValues::default()->equals($chosen));
    }

    public function test_an_identity_keeps_distinct_integer_company_ids(): void
    {
        $identity = PortalIdentity::fromArray(['mode' => 'company', 'scope' => 'group', 'company_ids' => ['2', 1, 2]]);

        $this->assertSame([2, 1], $identity->companyIds);
        $this->assertTrue($identity->equals(PortalIdentity::fromArray($identity->toArray())));
        $this->assertTrue(PortalIdentity::marketplace()->isMarketplace());
    }

    public function test_the_signature_of_the_skipped_companies_ignores_their_order_and_is_null_when_none_is_skipped(): void
    {
        $a = new PortalSkippedCompany(1, 'A', PortalSkippedCompany::SUSPENDED);
        $b = new PortalSkippedCompany(2, null, PortalSkippedCompany::MISSING);

        $this->assertNull(PortalHealth::signature([]));
        $this->assertSame(PortalHealth::signature([$a, $b]), PortalHealth::signature([$b, $a]));
        $this->assertNotSame(PortalHealth::signature([$a]), PortalHealth::signature([$a, $b]));
        $this->assertNotSame(
            PortalHealth::signature([$a]),
            PortalHealth::signature([new PortalSkippedCompany(1, 'A', PortalSkippedCompany::HIDDEN)]),
            'the reason is part of the set',
        );
    }
}
