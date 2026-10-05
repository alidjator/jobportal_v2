<?php

namespace Src\Company\Domain;

use Src\Shared\Domain\TranslatedText;

/**
 * A kind of document a company can be verified with, managed by admins. The slug is fixed when the type is created. A type is never
 * deleted: an inactive type accepts no new upload and does not count for the verification badge, but existing documents stay.
 */
final readonly class DocumentType
{
    public function __construct(
        public ?int $id,
        public string $slug,
        public TranslatedText $name,
        public bool $isRequired,
        public bool $isActive,
        public int $sortOrder,
    ) {}

    public function withChanges(TranslatedText $name, bool $isRequired, bool $isActive, int $sortOrder): self
    {
        return new self($this->id, $this->slug, $name, $isRequired, $isActive, $sortOrder);
    }

    /** Whether the badge calculation is affected by moving from this state to `$other` (only `is_required` and `is_active` matter). */
    public function affectsVerificationComparedTo(self $other): bool
    {
        return $this->isRequired !== $other->isRequired || $this->isActive !== $other->isActive;
    }
}
