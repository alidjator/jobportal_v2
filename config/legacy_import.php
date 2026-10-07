<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy application files
    |--------------------------------------------------------------------------
    |
    | The project folder of the legacy application (karir.pkp). Uploaded files (images, resumes) are looked for under its `public`, `storage/app` and
    | `storage/app/public` folders, by the path stored in the legacy database. Left empty, no legacy file can be read and every file is reported as skipped.
    */
    'files_root' => env('KARIR_PKP_FILES_ROOT', ''),

    // The largest legacy file read into memory, in bytes (the new size limits are checked separately).
    'max_file_bytes' => 20 * 1024 * 1024,
];
