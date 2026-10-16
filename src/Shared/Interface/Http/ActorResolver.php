<?php

namespace Src\Shared\Interface\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\ActorProvider;
use Src\Shared\Application\Company\CompanyMembershipResolver;

/** Builds the `Actor` of a use case from the account authenticated on the request. A company member's actor also carries their company and role. */
final readonly class ActorResolver
{
    public function __construct(private CompanyMembershipResolver $memberships) {}

    /** @throws AuthenticationException when the request carries no account that can act */
    public function fromRequest(Request $request): Actor
    {
        $account = $request->user();

        if (! $account instanceof ActorProvider) {
            throw new AuthenticationException;
        }

        $actor = $account->toActor();
        $supportGroup = $request->attributes->get('support_group_id');

        if ($actor->kind === ActorKind::Admin && is_int($supportGroup)) {
            return $actor->forSupport($supportGroup);
        }

        return $actor->kind === ActorKind::CompanyMember && $actor->id !== null
            ? $actor->withMembership($this->memberships->forUser($actor->id))
            : $actor;
    }
}
