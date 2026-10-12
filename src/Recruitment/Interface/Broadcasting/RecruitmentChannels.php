<?php

namespace Src\Recruitment\Interface\Broadcasting;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Src\Recruitment\Application\Video\VideoChannelAuthorizer;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\ActorProvider;
use Src\Shared\Application\Company\CompanyMembershipResolver;

/** The real-time channels of Recruitment. `interview-video.{interviewId}` is private: the panel of a video interview and its candidate. */
final class RecruitmentChannels
{
    public static function register(): void
    {
        Broadcast::channel('interview-video.{interviewId}', function (Authenticatable $user, string $interviewId): bool {
            if (! $user instanceof ActorProvider) {
                return false;
            }

            $actor = $user->toActor();

            if ($actor->kind === ActorKind::CompanyMember && $actor->id !== null) {
                $actor = $actor->withMembership(app(CompanyMembershipResolver::class)->forUser($actor->id));
            }

            return app(VideoChannelAuthorizer::class)->mayListen($actor, (int) $interviewId);
        });
    }
}
