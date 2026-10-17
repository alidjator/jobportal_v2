<?php

namespace Src\Recruitment\Application;

/** The link to the review page of a request in the company's client app: `<base>/review-requests/<id>`; empty or null means the app is not configured, so there is no link. */
final readonly class ReviewPageLink
{
    public function __construct(private ?string $baseUrl = null) {}

    public function to(int $requestId): ?string
    {
        return $this->baseUrl === null || trim($this->baseUrl) === '' ? null : rtrim(trim($this->baseUrl), '/').'/review-requests/'.$requestId;
    }
}
