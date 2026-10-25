<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Clock;
use Src\Shared\Application\Portal\PortalOwner;
use Src\Shared\Application\Portal\PortalPreview;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A token for two hours that shows the owner's draft on the career site. The token names the owner, not the draft: it shows the draft as it is when the page loads. */
final readonly class CreatePortalPreview
{
    public const int HOURS = 2;

    public function __construct(private Portals $portals, private PortalPreviewTokens $tokens, private Clock $clock) {}

    /** @throws BusinessRuleViolation `portal-draft-missing` */
    public function execute(PortalOwner $owner): PortalPreview
    {
        if ($this->portals->draft($owner) === null) {
            throw new BusinessRuleViolation('There is no draft to preview.', 'portal-draft-missing');
        }

        $expiresAt = $this->clock->now()->modify('+'.self::HOURS.' hours');

        return new PortalPreview($this->tokens->issue($owner, $expiresAt), $expiresAt);
    }
}
