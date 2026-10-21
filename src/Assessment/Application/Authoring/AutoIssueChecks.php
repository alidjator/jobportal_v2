<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\NormRepository;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\QuestionType;
use Src\Assessment\Domain\ReportTemplateRepository;
use Src\Assessment\Domain\Test;
use Src\Shared\Domain\Exceptions\ErrorMessage;

/**
 * What a battery needs before its reports may be issued automatically: its provider allows it, the responsible psychologist is an active psychologist of the
 * provider, no question is a drawing (a psychologist scores drawings), every norm range has an explanation and the battery has an explanation template. The
 * problems are listed in plain sentences, for `test-incomplete`.
 */
final readonly class AutoIssueChecks
{
    public function __construct(
        private ProviderRepository $providers,
        private PsychologistRepository $psychologists,
        private QuestionRepository $questions,
        private NormRepository $norms,
        private ReportTemplateRepository $templates,
    ) {}

    /** @return list<string|ErrorMessage> */
    public function problems(Test $test): array
    {
        $problems = [];
        $providerId = $test->owner->providerId;
        $provider = $providerId === null ? null : $this->providers->findById($providerId);

        if ($provider === null || ! $provider->autoIssueAllowed()) {
            $problems[] = 'The provider is not allowed to issue reports automatically.';
        }

        $responsible = $test->responsiblePsychologistId() === null ? null : $this->psychologists->findById($test->responsiblePsychologistId());

        if ($responsible === null || $responsible->providerId !== $providerId || ! $responsible->isActive()) {
            $problems[] = 'Name an active psychologist of the provider as the responsible psychologist.';
        }

        if ($this->questions->testHasType((int) $test->id, QuestionType::Drawing)) {
            $problems[] = 'A battery with a drawing question cannot issue reports automatically: a psychologist scores drawings.';
        }

        foreach ($this->norms->ofTest((int) $test->id) as $norm) {
            if ($norm->explanation === null) {
                $problems[] = new ErrorMessage('The norm of the scale :scale from :min to :max has no explanation.', ['scale' => $norm->scaleKey, 'min' => $norm->rawMin, 'max' => $norm->rawMax]);
            }
        }

        if ($this->templates->explanationTemplateOf((int) $test->id) === null) {
            $problems[] = 'The battery needs an explanation template.';
        }

        return $problems;
    }
}
