<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\SectionRules;
use Src\Assessment\Domain\TestKind;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Turns what an author submitted into a valid section, applying the format rules and the section time limit of the author's company. */
final readonly class SectionFactory
{
    public function __construct(private Settings $settings) {}

    /**
     * @throws BusinessRuleViolation `invalid-section`
     */
    public function build(Author $author, TestKind $kind, int $testId, SectionDraft $draft, ?int $id = null, int $position = 0): Section
    {
        SectionRules::assertFormatAllowed($kind, $draft->format);

        $settings = SectionRules::settings($draft->format, $draft->settings);
        $maxSeconds = $this->settings->integer(AssessmentSettings::MAX_SECTION_MINUTES, $author->companyId()) * 60;

        return new Section(
            $id,
            $testId,
            $position,
            SectionRules::title($draft->title),
            $draft->instructions === null || trim($draft->instructions) === '' ? null : trim($draft->instructions),
            $draft->format,
            SectionRules::duration($draft->format, $draft->durationSeconds, $settings, $maxSeconds),
            $draft->allowSkip,
            $draft->allowBack,
            $draft->allowJump,
            SectionRules::drawCount($draft->format, $draft->drawCount),
            $settings,
        );
    }
}
