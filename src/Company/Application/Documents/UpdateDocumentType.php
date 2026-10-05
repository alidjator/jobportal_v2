<?php

namespace Src\Company\Application\Documents;

use Src\Company\Application\Verification\BadgeChanges;
use Src\Company\Application\Verification\CompanyVerificationBadge;
use Src\Company\Domain\DocumentType;
use Src\Company\Domain\DocumentTypeRepository;
use Src\Company\Domain\Events\DocumentTypeVerificationRulesChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\TranslatedText;

/**
 * A platform admin changes a document type: names, order, required or optional, active or inactive. The slug never changes and the type is never
 * deleted. When `is_required` or `is_active` changes, every company is recalculated in the same transaction and the change is audited.
 */
final readonly class UpdateDocumentType
{
    public function __construct(
        private DocumentTypeRepository $types,
        private CompanyVerificationBadge $badge,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  array<array-key, mixed>  $name
     *
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the type does not exist
     */
    public function execute(Actor $actor, int $id, array $name, bool $isRequired, bool $isActive, int $sortOrder, ?string $ipAddress, ?string $userAgent): DocumentType
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can manage document types.');
        }

        $text = TranslatedText::fromArray($name);

        /** @var array{0: DocumentType, 1: DocumentType, 2: BadgeChanges} $result the type before and after, and the badges that changed */
        $result = $this->transactions->run(function () use ($id, $text, $isRequired, $isActive, $sortOrder): array {
            $this->types->lock($id);
            $before = $this->types->find($id) ?? throw EntityNotFound::of('Document type', $id);
            $after = $before->withChanges($text, $isRequired, $isActive, $sortOrder);

            $this->types->save($after);

            return [$before, $after, $after->affectsVerificationComparedTo($before) ? $this->badge->recalculateAll() : BadgeChanges::none()];
        });

        [$before, $after, $changes] = $result;

        if ($after->affectsVerificationComparedTo($before)) {
            $now = $this->clock->now();

            $this->events->dispatch([
                new DocumentTypeVerificationRulesChanged(
                    $id, $after->slug, $before->isRequired, $after->isRequired, $before->isActive, $after->isActive,
                    (int) $actor->id, $ipAddress, $userAgent, $now,
                ),
                ...$changes->granted($now),
                ...$changes->withdrawn($now),
            ]);
        }

        return $after;
    }
}
