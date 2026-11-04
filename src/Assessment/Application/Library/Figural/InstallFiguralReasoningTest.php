<?php

namespace Src\Assessment\Application\Library\Figural;

use Src\Assessment\Application\Authoring\AddQuestion;
use Src\Assessment\Application\Authoring\AddSection;
use Src\Assessment\Application\Authoring\CreateTest;
use Src\Assessment\Application\Authoring\PublishTest;
use Src\Assessment\Application\Authoring\QuestionDraft;
use Src\Assessment\Application\Authoring\SectionDraft;
use Src\Assessment\Application\Authoring\UploadQuestionImage;
use Src\Assessment\Domain\QuestionOption;
use Src\Assessment\Domain\QuestionType;
use Src\Assessment\Domain\SectionFormat;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestOwner;
use Src\Assessment\Domain\TestRepository;
use Src\Assessment\Domain\TestStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Writes the figural reasoning test (`FiguralReasoningContent`) into the system library as the given admin and publishes it, through the same authoring actions as the Console, so every rule of a library test
 * applies. Once only: a library test with the same title that is not archived is a 409. A run that fails half way leaves a draft, which the admin deletes before running it again.
 */
final readonly class InstallFiguralReasoningTest
{
    private const array LETTERS = ['A', 'B', 'C', 'D', 'E'];

    public function __construct(
        private TestRepository $tests,
        private CreateTest $createTest,
        private AddSection $addSection,
        private AddQuestion $addQuestion,
        private UploadQuestionImage $upload,
        private PublishTest $publish,
        private FigureRenderer $renderer,
    ) {}

    /**
     * @return int the id of the published test
     *
     * @throws StateConflict `library-test-exists`
     */
    public function execute(Actor $admin): int
    {
        $this->assertNotInstalled();

        $testId = (int) $this->createTest->execute(
            $admin, FiguralReasoningContent::TITLE, FiguralReasoningContent::DESCRIPTION, FiguralReasoningContent::INSTRUCTIONS, FiguralReasoningContent::CAPABILITIES,
        )->id;

        foreach (FiguralReasoningContent::sections() as $section) {
            $sectionId = (int) $this->addSection->execute($admin, $testId, new SectionDraft(
                $section->title, $section->instructions, SectionFormat::Questions, $section->seconds, true, true, true, null, [],
            ))->id;
            $number = 0;

            foreach ($section->items as $item) {
                $text = $item->isSample ? 'Contoh. '.$section->prompt : 'Soal '.++$number.'. '.$section->prompt;
                $this->addQuestion->execute($admin, $sectionId, $this->draft($admin, $testId, $section, $item, $text));
            }
        }

        $this->publish->execute($admin, $testId);

        return $testId;
    }

    private function draft(Actor $admin, int $testId, FiguralSection $section, FiguralItem $item, string $text): QuestionDraft
    {
        $stemImages = [];

        if ($section->layout !== FiguralLayout::OptionsOnly) {
            $stemImages[] = (int) $this->upload->execute($admin, $testId, $this->renderer->stem($section->layout, $item->stem), (string) $section->stemAlt)->id;
        }

        $options = [];
        $optionImages = [];

        foreach ($item->options as $index => $frame) {
            $letter = self::LETTERS[$index];
            $options[] = new QuestionOption($letter, $index === $item->key);
            $optionImages[] = (int) $this->upload->execute($admin, $testId, $this->renderer->frame($frame), sprintf(FiguralReasoningContent::OPTION_ALT, $letter))->id;
        }

        return new QuestionDraft(QuestionType::SingleChoice, $text, 1, null, $item->isSample, $options, null, null, null, $stemImages, $optionImages);
    }

    private function assertNotInstalled(): void
    {
        $page = $this->tests->pageOwnedBy(TestOwner::library(), null, FiguralReasoningContent::TITLE, new PageRequest(1, PageRequest::MAX_PER_PAGE));
        $installed = array_filter($page->items, static fn (Test $test): bool => $test->title() === FiguralReasoningContent::TITLE && $test->status() !== TestStatus::Archived);

        if ($installed !== []) {
            throw new StateConflict('The figural reasoning test is already in the library. Archive or delete it first.', 'library-test-exists');
        }
    }
}
