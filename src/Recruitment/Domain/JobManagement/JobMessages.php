<?php

namespace Src\Recruitment\Domain\JobManagement;

/**
 * Pesan untuk lowongan: memakai terjemahan `lang/*.json` bila kuncinya ada, selain itu teks Inggris
 * bawaan aplikasi lama (sebagian kunci belum ada di lang proyek ini).
 */
final class JobMessages
{
    private const DEFAULTS = [
        'job_created_successfully' => 'Job Created Successfully',
        'job_updated_successfully' => 'Job Updated Successfully',
        'job_cloned_successfully' => 'Job Cloned Successfully',
        'job_promote_successfully' => 'Job Promote Successfully',
        'job_status_updated_successfully' => 'Job status updated successfully',
        'job_not_found' => 'Job not found',
        'invalid_job_status' => 'Invalid job status',
        'you_have_no_featured_job_limit' => 'You have no featured job limit',
        'you_have_no_highlight_job_limit' => 'You have no highlight job limit',
        'you_are_not_authorized_to_perform_this_action' => 'You are not authorized to perform this action',
        'you_have_reached_your_plan_limit_please_upgrade_your_plan' => 'You have reached your plan limit. Please upgrade your plan.',
        'your_job_successfully_updated_please_wait_for_approve_changes' => 'Your job successfully updated. Please wait for approve changes',
        'job_has_been_created_and_published' => 'Job has been created and published',
        'job_has_been_created_and_waiting_for_admin_approval' => 'Job has been created and waiting for admin approval',
    ];

    public static function get(string $key): string
    {
        $translated = __($key);

        return $translated !== $key ? $translated : (self::DEFAULTS[$key] ?? $key);
    }
}
