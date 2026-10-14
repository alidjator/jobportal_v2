<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestOwner;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The checks every authoring action starts with: who the author is, and that the test being changed is theirs. */
final readonly class AuthoringAccess
{
    public function __construct(
        private CompanyDirectory $companies,
        private PsychologistRepository $psychologists,
        private ProviderRepository $providers,
        private TestRepository $tests,
        private InvitationRepository $invitations,
    ) {}

    /**
     * @throws AccessDenied `not-an-author`, `company-suspended`, `company-permission-required`, `psychologist-suspended` or `provider-suspended`
     */
    public function author(Actor $actor): Author
    {
        if ($actor->id === null) {
            throw new AccessDenied('Only a company member with the permission, a psychologist or an administrator can write tests.', 'not-an-author');
        }

        if ($actor->isAdmin()) {
            return new Author(TestOwner::library(), null, $actor->id);
        }

        if ($actor->kind === ActorKind::CompanyMember && $actor->membership !== null) {
            if (! $this->companies->isActive($actor->membership->companyId)) {
                throw new AccessDenied('This company is suspended.', 'company-suspended');
            }

            $actor->membership->require(CompanyPermission::AssessmentsAuthor);

            return new Author(TestOwner::company($actor->membership->companyId), $actor->id, null);
        }

        if ($actor->kind === ActorKind::Psychologist) {
            $psychologist = $this->psychologists->findByUserId($actor->id) ?? throw new AccessDenied('This account is not a psychologist of a provider.', 'not-an-author');

            if (! $psychologist->isActive()) {
                throw new AccessDenied('This psychologist account is suspended.', 'psychologist-suspended');
            }

            $provider = $this->providers->findById($psychologist->providerId);

            if ($provider === null || ! $provider->isActive()) {
                throw new AccessDenied('The provider of this psychologist is suspended.', 'provider-suspended');
            }

            return new Author(TestOwner::provider($psychologist->providerId), $actor->id, null);
        }

        throw new AccessDenied('Only a company admin, a psychologist or an administrator can write tests.', 'not-an-author');
    }

    /**
     * The test, when it belongs to the author; the same 404 whether it does not exist or belongs to someone else.
     *
     * @throws EntityNotFound
     */
    public function ownedTest(Author $author, int $testId, bool $forUpdate = false): Test
    {
        $test = $this->tests->find($testId, $forUpdate);

        if ($test === null || ! $test->owner->equals($author->owner)) {
            throw EntityNotFound::of('Test', $testId);
        }

        return $test;
    }

    /**
     * The test, locked for update, when it belongs to the author and its content may change: not archived, and not sent to a candidate yet (a test that has
     * invitations is locked, so the candidates and the results keep referring to what was sent; archive or clone it instead).
     *
     * @throws EntityNotFound
     * @throws StateConflict `test-archived` or `test-locked`
     */
    public function editableTest(Author $author, int $testId): Test
    {
        $test = $this->ownedTest($author, $testId, true);
        $test->assertEditable();

        if ($this->invitations->existsForTest($testId)) {
            throw new StateConflict('This test was already sent to candidates, so it can no longer be changed. Clone it to make a new version.', 'test-locked');
        }

        return $test;
    }
}
