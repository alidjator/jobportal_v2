<?php

namespace Tests\Unit\Shared\Documentation;

use Dedoc\Scramble\Support\Generator\InfoObject;
use Src\Shared\Infrastructure\Documentation\InfoWithContact;
use Tests\TestCase;

final class InfoWithContactTest extends TestCase
{
    public function test_the_contact_is_written_next_to_the_title_version_and_description(): void
    {
        $info = InfoWithContact::from((new InfoObject('ONESIS ERP API', '1.2.0'))->setDescription('About'), ['name' => 'Team', 'email' => 'team@example.test', 'url' => null]);

        $this->assertSame([
            'title' => 'ONESIS ERP API',
            'version' => '1.2.0',
            'description' => 'About',
            'contact' => ['name' => 'Team', 'email' => 'team@example.test'],
        ], $info->toArray());
    }

    public function test_a_given_title_replaces_the_one_of_the_generated_document(): void
    {
        $info = InfoWithContact::from(new InfoObject('Some App'), [], 'ONESIS ERP API');

        $this->assertSame('ONESIS ERP API', $info->toArray()['title']);
    }

    public function test_an_empty_contact_is_left_out(): void
    {
        $info = InfoWithContact::from(new InfoObject('ONESIS ERP API'), ['email' => null]);

        $this->assertArrayNotHasKey('contact', $info->toArray());
    }
}
