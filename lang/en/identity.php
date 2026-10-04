<?php

return [
    'email_verification' => [
        'subject' => 'Your verification code',
        'greeting' => 'Hello :name,',
        'intro' => 'Use this code to verify your email address:',
        'expiry' => 'The code is valid for :minutes minutes and can be used once.',
        'ignore' => 'If you did not create an account, you can ignore this email.',
    ],
    'password_reset' => [
        'subject' => 'Your password reset code',
        'greeting' => 'Hello :name,',
        'intro' => 'Use this code to set a new password:',
        'expiry' => 'The code is valid for :minutes minutes and can be used once.',
        'ignore' => 'If you did not ask to reset your password, you can ignore this email. Your password stays unchanged.',
    ],
];
