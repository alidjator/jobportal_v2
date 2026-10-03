<?php

namespace Src\Company\Application\Commands;

use Src\Company\Domain\BookmarkNotifier;
use Src\Company\Domain\BookmarkRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class ToggleCandidateBookmark
{
    public function __construct(
        private CompanyRepository $companies,
        private BookmarkRepository $bookmarks,
        private BookmarkNotifier $notifier,
    ) {}

    /**
     * @param  array{id:int,name:string,username:string}  $companyUser
     * @return string pesan hasil (ditambahkan / dihapus)
     */
    public function __invoke(array $companyUser, string $candidateUsername, ?int $categoryId): string
    {
        $companyId = $this->companies->idForUser($companyUser['id']);
        $candidate = $this->bookmarks->findCandidateByUsername($candidateUsername)
            ?? throw new EntityNotFound('Candidate not found');

        $categoryId = $categoryId ?: null;
        if ($categoryId !== null && ! $this->bookmarks->categoryExists($companyId, $categoryId)) {
            throw new EntityNotFound('Category not found');
        }

        if ($this->bookmarks->isBookmarked($companyId, $candidate['id'])) {
            $this->bookmarks->removeBookmark($companyId, $candidate['id']);

            return 'Candidate removed from bookmark list';
        }

        if ($categoryId !== null) {
            $remaining = $this->bookmarks->remainingCvViews($companyId);

            if ($remaining !== null) {
                if ($remaining <= 0) {
                    throw new BusinessRuleViolation('You have reached your limit for viewing candidate cv. Please upgrade your plan.');
                }

                $this->bookmarks->consumeCvView($companyId);
            }
        }

        $this->bookmarks->addBookmark($companyId, $candidate['id'], $categoryId);
        $this->notifier->bookmarked($companyUser, $candidate, $candidate['shortlisted_alert']);

        return 'Candidate added to bookmark list';
    }
}
