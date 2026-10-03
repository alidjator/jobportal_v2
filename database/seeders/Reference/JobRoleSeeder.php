<?php

namespace Database\Seeders\Reference;

/**
 * Job role (the merged roles and professions of the legacy system). The 24 values and their
 * alphabetical order come from the legacy `job_roles` table; the legacy `professions` table is empty
 * and the legacy table stores no slugs. Common English job titles stay in English in the Indonesian
 * names; the others were translated for this project.
 */
class JobRoleSeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_job_roles';
    }

    protected function rows(): array
    {
        return [
            ['name' => ['id' => 'Account Manager', 'en' => 'Account Manager']],
            ['name' => ['id' => 'Akuntansi', 'en' => 'Accounting']],
            ['name' => ['id' => 'Application Support', 'en' => 'Application Support']],
            ['name' => ['id' => 'Business Analyst', 'en' => 'Business Analyst']],
            ['name' => ['id' => 'Database Administrator', 'en' => 'Database Administrator']],
            ['name' => ['id' => 'Developer', 'en' => 'Developer']],
            ['name' => ['id' => 'Pengemudi', 'en' => 'Driver']],
            ['name' => ['id' => 'Keuangan', 'en' => 'Finance']],
            ['name' => ['id' => 'General Affair', 'en' => 'General Affair']],
            ['name' => ['id' => 'Infrastruktur', 'en' => 'Infrastructure']],
            ['name' => ['id' => 'Network Administrator', 'en' => 'Network Administrator']],
            ['name' => ['id' => 'Asisten Kantor', 'en' => 'Office Assistant']],
            ['name' => ['id' => 'Presales Infrastruktur', 'en' => 'Presales Infrastructure']],
            ['name' => ['id' => 'Direktur Utama', 'en' => 'President Director']],
            ['name' => ['id' => 'Kepala Departemen', 'en' => 'Department Head']],
            ['name' => ['id' => 'Admin Proyek', 'en' => 'Project Admin']],
            ['name' => ['id' => 'Project Manager', 'en' => 'Project Manager']],
            ['name' => ['id' => 'Quality Assurance', 'en' => 'Quality Assurance']],
            ['name' => ['id' => 'Sales Executive', 'en' => 'Sales Executive']],
            ['name' => ['id' => 'Server Administrator', 'en' => 'Server Administrator']],
            ['name' => ['id' => 'Service Desk', 'en' => 'Service Desk']],
            ['name' => ['id' => 'System Administrator', 'en' => 'System Administrator']],
            ['name' => ['id' => 'System Analyst', 'en' => 'System Analyst']],
            ['name' => ['id' => 'UI/UX Designer', 'en' => 'UI/UX Designer']],
        ];
    }
}
