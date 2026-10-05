<?php

namespace Src\Company\Application\Documents;

use Src\Company\Application\CompanySlugGenerator;
use Src\Company\Application\Verification\BadgeChanges;
use Src\Company\Application\Verification\CompanyVerificationBadge;
use Src\Company\Domain\DocumentType;
use Src\Company\Domain\DocumentTypeRepository;
use Src\Company\Domain\Events\DocumentTypeCreated;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\TranslatedText;

/**
 * A platform admin adds a document type. The slug comes from the English name once and never changes (a clash gets `-2`, `-3`). A new type
 * that is required and active takes the badge from companies that lack that document, so every company is recalculated in the same transaction.
 */
final readonly class CreateDocumentType
{
    private const int MAX_SLUG_LENGTH = 60;

    private const string FALLBACK_SLUG = 'document';

    public function __construct(
        private DocumentTypeRepository $types,
        private CompanySlugGenerator $slugs,
        private CompanyVerificationBadge $badge,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  array<array-key, mixed>  $name
     *
     * @throws AccessDenied when the actor is not an admin
     */
    public function execute(Actor $actor, array $name, bool $isRequired, bool $isActive, int $sortOrder, ?string $ipAddress, ?string $userAgent): DocumentType
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can manage document types.');
        }

        $text = TranslatedText::fromArray($name);

        /** @var array{0: DocumentType, 1: BadgeChanges} $result */
        $result = $this->transactions->run(function () use ($text, $isRequired, $isActive, $sortOrder): array {
            $created = $this->types->create(new DocumentType(null, $this->uniqueSlug($text), $text, $isRequired, $isActive, $sortOrder));

            return [$created, $isRequired && $isActive ? $this->badge->recalculateAll() : BadgeChanges::none()];
        });

        [$type, $changes] = $result;
        $now = $this->clock->now();

        $this->events->dispatch([
            new DocumentTypeCreated((int) $type->id, $type->slug, $type->isRequired, $type->isActive, (int) $actor->id, $ipAddress, $userAgent, $now),
            ...$changes->granted($now),
            ...$changes->withdrawn($now),
        ]);

        return $type;
    }

    private function uniqueSlug(TranslatedText $name): string
    {
        $values = $name->toArray();
        $base = $this->slugs->make($values['en'] !== '' ? $values['en'] : $values['id'], self::FALLBACK_SLUG);
        $slug = $base;

        for ($number = 2; $this->types->slugExists($slug); $number++) {
            $suffix = '-'.$number;
            $slug = mb_substr($base, 0, self::MAX_SLUG_LENGTH - mb_strlen($suffix)).$suffix;
        }

        return $slug;
    }
}
