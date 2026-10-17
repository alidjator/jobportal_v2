<?php

namespace Src\Shared\Infrastructure\Email;

use Illuminate\Container\Container;
use Src\Shared\Application\Email\CompanyEmailFrame;

/** For a mailable of a company's message: builds the frame of the email from the `company_id` and `company_name` among its variables; none without a `company_id`. */
trait FramesCompanyEmail
{
    /**
     * @param  array<string, string>  $variables
     * @return ?array{name: string, logo_url: ?string, notice: string}
     */
    private function companyFrame(array $variables, string $language): ?array
    {
        if (! isset($variables['company_id'])) {
            return null;
        }

        return Container::getInstance()->make(CompanyEmailFrame::class)->for((int) $variables['company_id'], $variables['company_name'] ?? '', $language);
    }
}
