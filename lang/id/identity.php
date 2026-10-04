<?php

return [
    'email_verification' => [
        'subject' => 'Kode verifikasi Anda',
        'greeting' => 'Halo :name,',
        'intro' => 'Gunakan kode ini untuk memverifikasi alamat email Anda:',
        'expiry' => 'Kode berlaku selama :minutes menit dan hanya dapat digunakan sekali.',
        'ignore' => 'Jika Anda tidak membuat akun, abaikan email ini.',
    ],
    'password_reset' => [
        'subject' => 'Kode reset kata sandi Anda',
        'greeting' => 'Halo :name,',
        'intro' => 'Gunakan kode ini untuk menetapkan kata sandi baru:',
        'expiry' => 'Kode berlaku selama :minutes menit dan hanya dapat digunakan sekali.',
        'ignore' => 'Jika Anda tidak meminta reset kata sandi, abaikan email ini. Kata sandi Anda tidak berubah.',
    ],
];
