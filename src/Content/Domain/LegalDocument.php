<?php

namespace Src\Content\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\TranslatedText;

/**
 * One version of the terms or the privacy policy. A draft can be edited and deleted freely; publishing locks it for good (users
 * accept a specific version, so its text must never change), and publishing a newer version archives the previous one.
 */
final class LegalDocument
{
    public function __construct(
        public readonly ?int $id,
        public readonly LegalDocumentType $type,
        public readonly int $version,
        private TranslatedText $title,
        private TranslatedText $body,
        private LegalDocumentStatus $status,
        private ?DateTimeImmutable $publishedAt,
        public readonly ?int $createdByAdminId,
    ) {}

    public static function draft(LegalDocumentType $type, int $version, TranslatedText $title, TranslatedText $body, ?int $createdByAdminId): self
    {
        return new self(null, $type, $version, $title, $body, LegalDocumentStatus::Draft, null, $createdByAdminId);
    }

    public function title(): TranslatedText
    {
        return $this->title;
    }

    public function body(): TranslatedText
    {
        return $this->body;
    }

    public function status(): LegalDocumentStatus
    {
        return $this->status;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function isDraft(): bool
    {
        return $this->status === LegalDocumentStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === LegalDocumentStatus::Published;
    }

    /** Whether people may read this version: published (the active one) or archived (the one they may have accepted). */
    public function isVisibleToPublic(): bool
    {
        return $this->status !== LegalDocumentStatus::Draft;
    }

    /** @throws StateConflict `legal-document-not-draft` once the document is published or archived */
    public function updateDraft(TranslatedText $title, TranslatedText $body): void
    {
        $this->assertDraft('A published document cannot be changed: publish a new version instead.');

        $this->title = $title;
        $this->body = $body;
    }

    /** @throws StateConflict `legal-document-not-draft` when the document is not a draft */
    public function assertDeletable(): void
    {
        $this->assertDraft('Only a draft can be deleted.');
    }

    /**
     * @throws StateConflict `legal-document-not-draft` when it is not a draft
     * @throws BusinessRuleViolation `legal-document-incomplete` when the title or body lacks a language
     */
    public function publish(DateTimeImmutable $now): void
    {
        $this->assertDraft('Only a draft can be published.');

        if (! $this->title->isCompleteInAllLanguages() || ! $this->body->isCompleteInAllLanguages()) {
            throw new BusinessRuleViolation('The title and the body must be filled in every language before publishing.', 'legal-document-incomplete');
        }

        $this->status = LegalDocumentStatus::Published;
        $this->publishedAt = $now;
    }

    /** Called when a newer version of the same type is published. */
    public function archive(): void
    {
        if ($this->status === LegalDocumentStatus::Published) {
            $this->status = LegalDocumentStatus::Archived;
        }
    }

    private function assertDraft(string $message): void
    {
        if (! $this->isDraft()) {
            throw new StateConflict($message, 'legal-document-not-draft');
        }
    }
}
