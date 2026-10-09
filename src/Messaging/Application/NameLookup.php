<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Company\CompanyDirectory;

/** The names shown in chat, looked up once each per request. An account that was erased reads as "Deleted user". */
final class NameLookup
{
    public const string DELETED_USER = 'Deleted user';

    /** @var array<int, string> */
    private array $users = [];

    /** @var array<int, string> */
    private array $companies = [];

    public function __construct(private readonly ApplicantContacts $contacts, private readonly CompanyDirectory $companyDirectory) {}

    public function user(int $userId): string
    {
        if (! isset($this->users[$userId])) {
            $contact = $this->contacts->contact($userId);
            $this->users[$userId] = $contact === null ? self::DELETED_USER : $contact->name;
        }

        return $this->users[$userId];
    }

    public function company(int $companyId): string
    {
        if (! isset($this->companies[$companyId])) {
            $this->companies[$companyId] = $this->companyDirectory->summaries([$companyId])[$companyId]->name ?? '';
        }

        return $this->companies[$companyId];
    }
}
