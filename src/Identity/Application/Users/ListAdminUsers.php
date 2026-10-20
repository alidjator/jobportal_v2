<?php

namespace Src\Identity\Application\Users;

use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Email\EmailSuppressions;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

/** The accounts for admins holding `users.view`, newest first, searchable by part of the name or the email and filterable by whether the account came from karir.pkp and whether it ever signed in. Listing is not audited; opening one account is. */
final readonly class ListAdminUsers
{
    public function __construct(private UserRepository $users, private EmailSuppressions $suppressions) {}

    /** @return Page<AdminUserView> */
    public function execute(?string $search, PageRequest $request, ?bool $imported = null, ?bool $signedIn = null): Page
    {
        $search = $search === null || trim($search) === '' ? null : trim($search);
        $page = $this->users->search($search, $request, $imported, $signedIn);
        $reasons = $this->suppressions->reasons(array_map(fn (User $user): string => $user->email(), $page->items));

        return new Page(
            array_map(fn (User $user): AdminUserView => new AdminUserView($user, $reasons[mb_strtolower($user->email())] ?? null), $page->items),
            $page->total,
            $request,
        );
    }
}
