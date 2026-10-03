<?php

namespace Src\Recruitment\Infrastructure;

use Src\Recruitment\Infrastructure\JobBoard\JobBoardServiceProvider;
use Src\Recruitment\Infrastructure\JobManagement\JobManagementServiceProvider;
use Src\Shared\Infrastructure\ContextServiceProvider;

/**
 * Recruitment dibagi dua sub-area agar bisa dikerjakan terpisah:
 * - JobBoard      : sisi publik/kandidat (jelajah lowongan, lamar, bookmark, alert, home)
 * - JobManagement : sisi perusahaan (buat/ubah lowongan, kelola lamaran)
 */
class RecruitmentServiceProvider extends ContextServiceProvider
{
    public function register(): void
    {
        $this->app->register(JobBoardServiceProvider::class);
        $this->app->register(JobManagementServiceProvider::class);
    }
}
