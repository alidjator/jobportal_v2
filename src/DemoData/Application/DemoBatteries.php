<?php

namespace Src\DemoData\Application;

use Src\Assessment\Application\Admin\GrantBattery;
use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Application\Authoring\AddQuestion;
use Src\Assessment\Application\Authoring\AddSection;
use Src\Assessment\Application\Authoring\CreateTest;
use Src\Assessment\Application\Authoring\PublishTest;
use Src\Assessment\Application\Authoring\QuestionDraft;
use Src\Assessment\Application\Authoring\ReplaceNorms;
use Src\Assessment\Application\Authoring\SaveExplanationTemplate;
use Src\Assessment\Application\Authoring\SaveRecommendationRules;
use Src\Assessment\Application\Authoring\SaveReportTemplate;
use Src\Assessment\Application\Authoring\SectionDraft;
use Src\Assessment\Domain\QuestionOption;
use Src\Platform\Application\Settings\SetCompanySetting;
use Src\Shared\Application\Actor;

/**
 * The psychological batteries of the demo provider (`DemoBatteryContent`): the lead psychologist writes each one (sections, questions, norm table, report
 * and explanation templates, recommendation rule) and publishes it; the platform admin turns the battery add-on on for the verified company and grants it
 * the batteries, so its team can send them.
 */
final readonly class DemoBatteries
{
    public function __construct(
        private CreateTest $createTest,
        private AddSection $addSection,
        private AddQuestion $addQuestion,
        private ReplaceNorms $replaceNorms,
        private SaveReportTemplate $saveReport,
        private SaveExplanationTemplate $saveExplanation,
        private SaveRecommendationRules $saveRules,
        private PublishTest $publishTest,
        private GrantBattery $grantBattery,
        private SetCompanySetting $setCompanySetting,
    ) {}

    /**
     * @param  bool  $full  whether the full-size batteries are written too, which takes several minutes
     * @return array<string, int> the id of each published battery by key
     */
    public function build(Actor $admin, int $leadUserId, int $companyId, bool $full): array
    {
        $author = Actor::psychologist($leadUserId);
        $ids = [];

        foreach (DemoBatteryContent::keys($full) as $key) {
            $ids[$key] = $this->write($author, DemoBatteryContent::battery($key));
        }

        $this->setCompanySetting->execute($admin, $companyId, AssessmentSettings::PSYCHOLOGICAL_ENABLED, true, null, DemoCast::USER_AGENT);

        foreach ($ids as $testId) {
            $this->grantBattery->execute($admin, $companyId, $testId);
        }

        return $ids;
    }

    /** @param  array<string, mixed>  $battery */
    private function write(Actor $author, array $battery): int
    {
        $testId = (int) $this->createTest->execute($author, $battery['title'], $battery['description'], $battery['instructions'], $battery['capabilities'])->id;

        foreach ($battery['sections'] as $section) {
            $created = $this->addSection->execute($author, $testId, new SectionDraft(
                $section['title'], $section['instructions'], $section['format'], $section['minutes'] === null ? null : $section['minutes'] * 60, true, true, true, null, $section['settings'],
            ));

            foreach ($section['questions'] as $question) {
                $this->addQuestion->execute($author, (int) $created->id, new QuestionDraft(
                    $question['type'], $question['text'], 1, $question['scale'], false,
                    array_map(static fn (array $option): QuestionOption => new QuestionOption($option[0], $option[1], $option[2]), $question['options']),
                ));
            }
        }

        $this->replaceNorms->execute($author, $testId, $battery['norms']);
        $this->saveReport->execute($author, $testId, $battery['report']);
        $this->saveExplanation->execute($author, $testId, $battery['explanation']);
        $this->saveRules->execute($author, $testId, $battery['key_scales'], $battery['recommended_min'], $battery['not_yet_below']);
        $this->publishTest->execute($author, $testId);

        return $testId;
    }
}
