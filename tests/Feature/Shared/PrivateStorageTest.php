<?php

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Route;
use Tests\FeatureTestCase;

/** Files have no URL of their own: they leave the API only through endpoints that check access (architecture.md section 2.7). */
final class PrivateStorageTest extends FeatureTestCase
{
    public function test_the_private_disk_is_not_served_by_laravel(): void
    {
        $this->assertFalse((bool) config('filesystems.disks.local.serve'));

        $storageRoutes = array_filter(
            Route::getRoutes()->getRoutes(),
            fn ($route): bool => str_starts_with($route->uri(), 'storage/') || $route->uri() === 'storage',
        );

        $this->assertSame([], array_map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri(), $storageRoutes));
    }

    public function test_nothing_answers_at_the_storage_paths(): void
    {
        $this->getJson('/storage/company/logos/anything.jpg')->assertNotFound();
        $this->putJson('/storage/company/logos/anything.jpg', [])->assertNotFound();
        $this->getJson('/storage/company/logos/anything.jpg?expires=9999999999&signature=abc')->assertNotFound();
    }

    public function test_there_is_no_public_symlink_to_stored_files(): void
    {
        $this->assertFalse(is_link(public_path('storage')));
        $this->assertFalse(file_exists(public_path('storage')));
    }
}
