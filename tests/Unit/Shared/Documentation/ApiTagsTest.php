<?php

namespace Tests\Unit\Shared\Documentation;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Src\Shared\Infrastructure\Documentation\ApiTags;
use Tests\TestCase;

final class ApiTagsTest extends TestCase
{
    /** @return list<string> the paths of every route of the API (the document pages and the framework routes are not part of it) */
    private function apiPaths(): array
    {
        $paths = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            /** @var LaravelRoute $route */
            if (str_starts_with($route->uri(), 'api/v1/')) {
                $paths[] = $route->uri();
            }
        }

        return array_values(array_unique($paths));
    }

    public function test_every_route_belongs_to_a_group(): void
    {
        $others = array_values(array_filter($this->apiPaths(), static fn (string $path): bool => ApiTags::forPath($path) === ApiTags::OTHER));

        $this->assertSame([], $others, 'Add a pattern for these routes to ApiTags::groups().');
    }

    public function test_a_route_goes_to_the_group_of_its_feature(): void
    {
        $this->assertSame('Authentication', ApiTags::forPath('api/v1/auth/login'));
        $this->assertSame('Admin: Reviews', ApiTags::forPath('api/v1/admin/reviews/{id}/approve'));
        $this->assertSame('Company reviews', ApiTags::forPath('api/v1/companies/{slug}/reviews'));
        $this->assertSame('Company reviews', ApiTags::forPath('api/v1/company/reviews/{id}/reply'));
        $this->assertSame('Campaigns', ApiTags::forPath('api/v1/company/campaigns/{id}/qr'));
        $this->assertSame('Admin: Campaigns and newsletter', ApiTags::forPath('api/v1/admin/campaigns'));
        $this->assertSame('Interviews and offers', ApiTags::forPath('api/v1/me/applications/{id}/offer/accept'));
        $this->assertSame('Interviews and offers', ApiTags::forPath('api/v1/company/applications/{id}/interviews'));
        $this->assertSame('Applications', ApiTags::forPath('api/v1/me/applications'));
        $this->assertSame('Recruitment pipeline', ApiTags::forPath('api/v1/company/jobs/{id}/applications/bulk-move'));
        $this->assertSame('Company jobs', ApiTags::forPath('api/v1/company/jobs/{id}/publish'));
        $this->assertSame('Candidate search', ApiTags::forPath('api/v1/company/jobs/{id}/candidates'));
        $this->assertSame('Chat', ApiTags::forPath('api/v1/company/messages/{id}/attachments/{attachment}'));
        $this->assertSame('Reference data', ApiTags::forPath('api/v1/job-roles'));
        $this->assertSame('Admin: Reference data', ApiTags::forPath('api/v1/admin/job-roles/{id}'));
        $this->assertSame('Job search', ApiTags::forPath('api/v1/salary-insight'));
    }

    public function test_the_group_names_are_unique(): void
    {
        $names = array_column(ApiTags::groups(), 'name');

        $this->assertSame($names, array_values(array_unique($names)));
    }
}
