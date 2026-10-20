<?php

namespace Src\Identity\Infrastructure\Mail;

use Illuminate\Contracts\Config\Repository;
use Src\Identity\Application\Activation\ActivationLinks;
use Src\Identity\Domain\UserType;

/** The activation and sign-in pages of the client applications, from `config/app.php` (`JOB_SEEKER_ACTIVATION_URL`, `COMPANY_ACTIVATION_URL`, `JOB_SEEKER_SIGN_IN_URL`). */
final readonly class ConfigActivationLinks implements ActivationLinks
{
    public function __construct(private Repository $config) {}

    public function activationUrl(UserType $audience): ?string
    {
        return $this->url($audience === UserType::CompanyMember ? 'app.company_activation_url' : 'app.job_seeker_activation_url');
    }

    public function signInUrl(): ?string
    {
        return $this->url('app.job_seeker_sign_in_url');
    }

    private function url(string $key): ?string
    {
        $value = $this->config->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
