<?php

namespace Src\DemoData\Application;

use Src\Assessment\Application\Authoring\AddQuestion;
use Src\Assessment\Application\Authoring\AddSection;
use Src\Assessment\Application\Authoring\CreateTest;
use Src\Assessment\Application\Authoring\PublishTest;
use Src\Assessment\Application\Authoring\QuestionDraft;
use Src\Assessment\Application\Authoring\SectionDraft;
use Src\Assessment\Domain\QuestionOption;
use Src\Assessment\Domain\SectionFormat;
use Src\Shared\Application\Actor;

/** Writes and publishes a skill test of the shape `DemoAssessmentContent::tests()` describes, as the author the actor is (a company admin or a platform admin). */
final readonly class DemoTestWriter
{
    public function __construct(
        private CreateTest $createTest,
        private AddSection $addSection,
        private AddQuestion $addQuestion,
        private PublishTest $publishTest,
    ) {}

    /**
     * @param  array<string, mixed>  $content
     * @return int the id of the published test
     */
    public function write(Actor $author, array $content): int
    {
        $testId = (int) $this->createTest->execute($author, $content['title'], $content['description'], $content['instructions'], $content['capabilities'])->id;

        foreach ($content['sections'] as $section) {
            $created = $this->addSection->execute($author, $testId, new SectionDraft(
                $section['title'], $section['instructions'], SectionFormat::Questions, $section['minutes'] * 60, true, true, true, null, [],
            ));

            foreach ($section['questions'] as [$type, $text, $points, $options]) {
                $this->addQuestion->execute($author, (int) $created->id, new QuestionDraft(
                    $type, $text, $points, null, false, array_map(static fn (array $option): QuestionOption => new QuestionOption($option[0], $option[1]), $options),
                ));
            }
        }

        $this->publishTest->execute($author, $testId);

        return $testId;
    }
}
