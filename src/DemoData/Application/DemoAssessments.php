<?php

namespace Src\DemoData\Application;

use LogicException;
use Src\Assessment\Application\Defaults\SetJobDefault;
use Src\Company\Domain\MemberPreset;
use Src\Recruitment\Application\ListStages;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;

/**
 * The skill tests of the verified company (`DemoAssessmentContent`): the company admin writes and publishes them, then the recruiter sets each job's
 * default test for the company's default assessment stage, with the score from which an application moves on to the default interview stage and the
 * members who score the long-text answers (the UI/UX test has a case study). The applications that enter the assessment stage are taken through the test by `DemoMyWork`.
 */
final readonly class DemoAssessments
{
    public function __construct(
        private DemoTestWriter $writer,
        private SetJobDefault $setJobDefault,
        private ListStages $stages,
        private DemoActors $actors,
    ) {}

    /**
     * @param  array<string, array{company_id: int, admin_id: int, members: array<string, int>}>  $teams
     * @param  array<string, int>  $jobIds
     * @return int how many tests were made
     */
    public function build(array $teams, array $jobIds): int
    {
        $team = $teams[DemoCast::SINAR];
        $admin = $this->actors->member($team['admin_id']);
        $recruiter = $this->actors->member($team['members'][MemberPreset::Recruiter->value]);
        $testIds = [];

        foreach (DemoAssessmentContent::tests() as $key => $content) {
            $testIds[$key] = $this->writer->write($admin, $content);
        }

        $assessmentStage = $this->defaultStageId($recruiter, StageType::Assessment);
        $interviewStage = $this->defaultStageId($recruiter, StageType::Interview);

        foreach (DemoAssessmentContent::jobDefaults() as $job => $default) {
            [$test, $days, $advanceFrom] = $default;
            $assessors = array_map(static fn (string $preset): int => $team['members'][$preset], $default[3] ?? []);

            $this->setJobDefault->execute(
                $recruiter, $jobIds[$job], $assessmentStage, $testIds[$test], $days, $assessors, $advanceFrom, $advanceFrom === null ? null : $interviewStage,
            );
        }

        return count($testIds);
    }

    private function defaultStageId(Actor $recruiter, StageType $type): int
    {
        foreach ($this->stages->execute($recruiter) as $stage) {
            if ($stage->isDefault && $stage->type === $type) {
                return (int) $stage->id;
            }
        }

        throw new LogicException("The company has no default {$type->value} stage.");
    }
}
