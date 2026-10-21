<?php

namespace Src\DemoData\Application;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Src\Company\Domain\MemberPreset;
use Src\Job\Application\CloseJob;
use Src\Recruitment\Application\ApplyToJob;
use Src\Recruitment\Application\ApplyToJobInput;
use Src\Recruitment\Application\InterviewInput;
use Src\Recruitment\Application\ListStages;
use Src\Recruitment\Application\OfferInput;
use Src\Recruitment\Application\OfferTermsInput;
use Src\Recruitment\Application\ProcessApplication;
use Src\Recruitment\Application\RespondToOffer;
use Src\Recruitment\Application\WithdrawMyApplication;
use Src\Recruitment\Domain\EmploymentType;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\OfferWorkMode;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\SalaryBasis;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;

/**
 * The applications of `DemoCast::applications()`: each job seeker applies with their resume, then the team moves the application to where it ends (the
 * recruiter in the verified company, the company admin in the other one). An interview is scheduled online with the interviewer, an offer is sent with
 * the move, a hire is an offer the job seeker accepts, and a withdrawal is the job seeker's own. Jobs meant to end closed are closed last.
 */
final readonly class DemoApplications
{
    public function __construct(
        private ApplyToJob $apply,
        private ProcessApplication $process,
        private ListStages $stages,
        private RespondToOffer $respondToOffer,
        private WithdrawMyApplication $withdraw,
        private CloseJob $closeJob,
        private DemoActors $actors,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, array{company_id: int, admin_id: int, members: array<string, int>}>  $teams
     * @param  array<string, int>  $jobIds
     * @param  list<array{user_id: int, resume_id: int}>  $seekers
     * @return int how many applications were made
     */
    public function build(array $teams, array $jobIds, array $seekers): int
    {
        $jobs = DemoCast::jobs();
        $interviewerId = $teams[DemoCast::SINAR]['members'][MemberPreset::Interviewer->value];
        $interviews = 0;

        foreach (DemoCast::applications() as $plan) {
            $seeker = $seekers[$plan['seeker']];
            $job = $jobs[$plan['job']];
            $team = $this->teamActor($teams, $job['company']);
            $seekerActor = Actor::jobSeeker($seeker['user_id']);

            $application = $this->apply->execute($seekerActor, $jobIds[$plan['job']], new ApplyToJobInput($seeker['resume_id'], null, []));
            $id = (int) $application->id;

            $this->advance($plan['outcome'], $team, $seekerActor, $id, $job, $interviewerId, $interviews);
        }

        foreach ($jobs as $key => $job) {
            if ($job['state'] === 'closed') {
                $this->closeJob->execute($this->teamActor($teams, $job['company']), $jobIds[$key], null, DemoCast::USER_AGENT);
            }
        }

        return count(DemoCast::applications());
    }

    /** @param  array{title: string, salary: array{int, int}}  $job */
    private function advance(StageType $outcome, Actor $team, Actor $seeker, int $id, array $job, int $interviewerId, int &$interviews): void
    {
        if ($outcome === StageType::Applied) {
            return;
        }

        if ($outcome === StageType::Rejected) {
            $this->process->reject($team, $id, RejectionReason::QualificationsMismatch, null);
        } elseif ($outcome === StageType::Withdrawn) {
            $this->withdraw->execute($seeker, $id, null);
        } elseif ($outcome === StageType::Screening) {
            $this->process->move($team, $id, $this->stageId($team, StageType::Screening));
        } elseif ($outcome === StageType::Interview) {
            $this->process->move($team, $id, $this->stageId($team, StageType::Interview), $this->interview($interviewerId, $interviews++));
        } elseif ($outcome === StageType::Offer || $outcome === StageType::Hired) {
            $this->process->move($team, $id, $this->stageId($team, StageType::Offer), offer: $this->offer($job['title'], $job['salary'][0]));

            if ($outcome === StageType::Hired) {
                $this->respondToOffer->accept($seeker, $id);
            }
        } else {
            throw new LogicException("The demo has no step for the {$outcome->value} stage.");
        }
    }

    /** @param  array<string, array{company_id: int, admin_id: int, members: array<string, int>}>  $teams */
    private function teamActor(array $teams, string $company): Actor
    {
        $team = $teams[$company];

        return $this->actors->member($company === DemoCast::SINAR ? $team['members'][MemberPreset::Recruiter->value] : $team['admin_id']);
    }

    private function stageId(Actor $team, StageType $type): int
    {
        foreach ($this->stages->execute($team) as $stage) {
            if ($stage->isDefault && $stage->type === $type) {
                return (int) $stage->id;
            }
        }

        throw new LogicException("The company has no default {$type->value} stage.");
    }

    /** An online interview at 10:00 Jakarta time, three days from now and one more day for each earlier interview of the run. */
    private function interview(int $interviewerId, int $earlier): InterviewInput
    {
        $jakarta = new DateTimeZone('Asia/Jakarta');
        $day = $this->clock->now()->setTimezone($jakarta)->modify('+'.(3 + $earlier).' days')->format('Y-m-d');

        return new InterviewInput(
            new DateTimeImmutable($day.' 10:00:00', $jakarta), 60, InterviewFormat::Online, null, 'https://meet.example.test/onesis-demo-'.($earlier + 1), $interviewerId,
        );
    }

    /** An offer valid for ten days, starting in thirty: a permanent position with a probation, hybrid, gross salary. */
    private function offer(string $position, int $salary): OfferInput
    {
        $now = $this->clock->now();
        $start = $now->setTimezone(new DateTimeZone('Asia/Jakarta'))->modify('+30 days')->format('Y-m-d');

        return new OfferInput(
            $position, $salary, new DateTimeImmutable($start, new DateTimeZone('UTC')), 'Selamat, kami senang menawarkan posisi ini kepada Anda.', $now->modify('+10 days'),
            new OfferTermsInput(EmploymentType::Permanent, null, 3, OfferWorkMode::Hybrid, 'Jakarta', SalaryBasis::Gross, [], []),
        );
    }
}
