<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestKind;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Applicant\ApplicantContacts;

/** Builds the team's views of invitations: the tests and the assessors' names are read once for the whole list. */
final readonly class InvitationViews
{
    public function __construct(private TestRepository $tests, private ApplicantContacts $accounts) {}

    public function one(Invitation $invitation): InvitationView
    {
        return $this->many([$invitation])[0];
    }

    /**
     * @param  list<Invitation>  $invitations
     * @return list<InvitationView>
     */
    public function many(array $invitations): array
    {
        $tests = [];

        foreach (array_unique(array_map(static fn (Invitation $invitation): int => $invitation->testId, $invitations)) as $testId) {
            $test = $this->tests->find($testId);

            if ($test instanceof Test) {
                $tests[$testId] = $test;
            }
        }

        $names = $this->accounts->names(array_values(array_unique(array_merge(...array_map(static fn (Invitation $invitation): array => $invitation->assessorIds, $invitations)))));

        return array_map(static fn (Invitation $invitation): InvitationView => new InvitationView(
            $invitation,
            isset($tests[$invitation->testId]) ? $tests[$invitation->testId]->title() : '',
            isset($tests[$invitation->testId]) ? $tests[$invitation->testId]->kind() : TestKind::Skill,
            array_map(static fn (int $userId): array => ['id' => $userId, 'name' => $names[$userId] ?? ''], $invitation->assessorIds),
        ), $invitations);
    }
}
