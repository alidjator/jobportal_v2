<?php

namespace Tests\Unit\Content\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Content\Domain\LegalDocument;
use Src\Content\Domain\LegalDocumentStatus;
use Src\Content\Domain\LegalDocumentType;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\TranslatedText;

final class LegalDocumentTest extends TestCase
{
    private function text(string $id = 'Teks', string $en = 'Text'): TranslatedText
    {
        return TranslatedText::fromArray(['id' => $id, 'en' => $en]);
    }

    private function draft(?TranslatedText $title = null, ?TranslatedText $body = null): LegalDocument
    {
        return LegalDocument::draft(LegalDocumentType::Terms, 1, $title ?? $this->text(), $body ?? $this->text(), 7);
    }

    public function test_a_new_document_is_a_draft_that_the_public_cannot_see(): void
    {
        $document = $this->draft();

        $this->assertTrue($document->isDraft());
        $this->assertFalse($document->isVisibleToPublic());
        $this->assertNull($document->publishedAt());
        $this->assertSame(7, $document->createdByAdminId);
    }

    public function test_a_draft_can_be_edited_and_deleted(): void
    {
        $document = $this->draft();

        $document->updateDraft($this->text('Judul baru', 'New title'), $this->text('Isi baru', 'New body'));
        $document->assertDeletable();

        $this->assertSame('New title', $document->title()->toArray()['en']);
    }

    public function test_publishing_needs_the_title_and_the_body_in_every_language(): void
    {
        foreach ([
            [TranslatedText::fromArray(['id' => 'Judul']), $this->text()],
            [$this->text(), TranslatedText::fromArray(['en' => 'Body'])],
        ] as [$title, $body]) {
            try {
                $this->draft($title, $body)->publish(new DateTimeImmutable);
                $this->fail('An incomplete document must not be published.');
            } catch (BusinessRuleViolation $e) {
                $this->assertSame('legal-document-incomplete', $e->errorCode());
            }
        }
    }

    public function test_publishing_locks_the_document(): void
    {
        $document = $this->draft();
        $now = new DateTimeImmutable('2026-10-03 10:00:00');

        $document->publish($now);

        $this->assertTrue($document->isPublished());
        $this->assertTrue($document->isVisibleToPublic());
        $this->assertSame($now, $document->publishedAt());

        foreach ([
            fn () => $document->updateDraft($this->text(), $this->text()),
            fn () => $document->assertDeletable(),
            fn () => $document->publish($now),
        ] as $action) {
            try {
                $action();
                $this->fail('A published document must be locked.');
            } catch (StateConflict $e) {
                $this->assertSame('legal-document-not-draft', $e->errorCode());
            }
        }
    }

    public function test_archiving_a_published_document_keeps_it_readable_and_locked(): void
    {
        $document = $this->draft();
        $document->publish(new DateTimeImmutable);

        $document->archive();

        $this->assertSame(LegalDocumentStatus::Archived, $document->status());
        $this->assertTrue($document->isVisibleToPublic());
        $this->expectException(StateConflict::class);
        $document->assertDeletable();
    }

    public function test_archiving_a_draft_changes_nothing(): void
    {
        $document = $this->draft();

        $document->archive();

        $this->assertTrue($document->isDraft());
    }
}
