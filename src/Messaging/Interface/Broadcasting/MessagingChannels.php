<?php

namespace Src\Messaging\Interface\Broadcasting;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Src\Messaging\Application\ChannelAuthorizer;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\ActorProvider;
use Src\Shared\Application\Company\CompanyMembershipResolver;

/**
 * The real-time channels. `conversations.{id}` is private: only the people who may read the conversation. `company-presence.{companyId}` is a presence channel only
 * the company's own members can join, and it names a member by id only: a job seeker is never a member, so a company never sees who is looking at its chat, and
 * the job seeker learns whether the company is online from the server instead (`company_online`).
 */
final class MessagingChannels
{
    public static function register(): void
    {
        Broadcast::channel('conversations.{conversationId}', function (Authenticatable $user, string $conversationId): bool {
            $actor = self::actorOf($user);

            return $actor !== null && app(ChannelAuthorizer::class)->mayJoinConversation($actor, (int) $conversationId);
        });

        Broadcast::channel('company-presence.{companyId}', function (Authenticatable $user, string $companyId): array|false {
            $actor = self::actorOf($user);

            return $actor !== null && app(ChannelAuthorizer::class)->mayJoinCompanyPresence($actor, (int) $companyId)
                ? ['id' => (int) $user->getAuthIdentifier()]
                : false;
        });
    }

    private static function actorOf(Authenticatable $user): ?Actor
    {
        if (! $user instanceof ActorProvider) {
            return null;
        }

        $actor = $user->toActor();

        return $actor->kind === ActorKind::CompanyMember && $actor->id !== null
            ? $actor->withMembership(app(CompanyMembershipResolver::class)->forUser($actor->id))
            : $actor;
    }
}
