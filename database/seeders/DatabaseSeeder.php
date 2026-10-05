<?php

namespace Database\Seeders;

use Database\Seeders\Company\CompanyDocumentTypeSeeder;
use Database\Seeders\Reference\LocationSeeder;
use Database\Seeders\Reference\ReferenceDataSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the data every installation needs.
     */
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            LocationSeeder::class,
            CompanyDocumentTypeSeeder::class,
        ]);
    }
}
