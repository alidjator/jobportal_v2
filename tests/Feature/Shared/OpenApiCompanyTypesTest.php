<?php

namespace Tests\Feature\Shared;

use Dedoc\Scramble\Generator;
use Tests\FeatureTestCase;

/** The generated OpenAPI document describes the company dashboard, task list and job resources exactly, so the typed client needs no casts. */
final class OpenApiCompanyTypesTest extends FeatureTestCase
{
    /** @var array<string, mixed>|null */
    private static ?array $document = null;

    /** @return array<string, mixed> */
    private function schema(string $name): array
    {
        self::$document ??= app(Generator::class)();

        /** @var array<string, mixed> */
        return self::$document['components']['schemas'][$name];
    }

    public function test_the_company_dashboard_cards_are_an_object_of_optional_typed_cards(): void
    {
        $cards = $this->schema('CompanyDashboardResource')['properties']['cards'];

        $this->assertSame('object', $cards['type']);
        $this->assertSame(['jobs', 'applications', 'schedule', 'job_performance', 'time_to_fill', 'messages', 'notifications'], array_keys($cards['properties']));
        $this->assertArrayNotHasKey('required', $cards);
        $this->assertSame(['by_status', 'total'], $cards['properties']['jobs']['required']);
        $this->assertSame(['interviews', 'offers_waiting'], $cards['properties']['schedule']['required']);
        $this->assertSame(['jobs', 'views_daily', 'applications_daily'], $cards['properties']['job_performance']['required']);
        $this->assertSame(['period_days', 'filled_jobs', 'median_days', 'open_jobs'], $cards['properties']['time_to_fill']['required']);
    }

    public function test_the_job_seeker_and_admin_dashboards_are_typed_too(): void
    {
        $this->assertSame(['profile', 'applications', 'interviews', 'invitations', 'messages', 'notifications'], array_keys($this->schema('JobSeekerDashboardResource')['properties']['cards']['properties']));
        $this->assertContains('operations', array_keys($this->schema('AdminDashboardResource')['properties']['cards']['properties']));
    }

    public function test_the_task_list_has_a_generated_at_and_typed_items(): void
    {
        $schema = $this->schema('CompanyTasksResource');

        $this->assertSame(['generated_at', 'items'], $schema['required']);
        $this->assertSame('array', $schema['properties']['items']['type']);
        $this->assertSame(['key', 'count', 'filter'], $schema['properties']['items']['items']['required']);
    }

    public function test_a_job_has_typed_identity_fields_and_an_optional_status_reason(): void
    {
        $schema = $this->schema('JobDetailResource');

        $this->assertSame('integer', $schema['properties']['id']['type']);
        $this->assertSame('string', $schema['properties']['slug']['type']);
        $this->assertSame('string', $schema['properties']['title']['type']);
        $this->assertSame('#/components/schemas/JobStatus', $schema['properties']['status']['$ref']);
        $this->assertSame(['string', 'null'], $schema['properties']['status_reason']['type']);
        $this->assertNotContains('status_reason', $schema['required']);
        $this->assertArrayNotHasKey('additionalProperties', $schema);

        foreach (['category_name', 'salary_period_name', 'province_name', 'village_name', 'location_label'] as $field) {
            $this->assertSame(['string', 'null'], $schema['properties'][$field]['type'], $field);
            $this->assertContains($field, $schema['required']);
        }

        $this->assertSame('#/components/schemas/JobStatus', $this->schema('JobListResource')['properties']['status']['$ref']);
    }
}
