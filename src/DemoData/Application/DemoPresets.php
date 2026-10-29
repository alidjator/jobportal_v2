<?php

namespace Src\DemoData\Application;

use LogicException;
use Src\Identity\Application\Users\ListAdminUsers;
use Src\Recruitment\Application\CreateScreeningPreset;
use Src\Recruitment\Application\CreateScreeningQuestion;
use Src\Recruitment\Application\ListScreeningPresets;
use Src\Recruitment\Application\ListScreeningQuestions;
use Src\Recruitment\Domain\QuestionType;
use Src\Recruitment\Domain\ScreeningQuestion;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The demo screening presets (`docs/design/phase-8-demo-data.md`): the company admin of each company saves the presets of {@see DemoPresetContent} from the questions of the library, adding the few questions the library
 * lacks. A question is found in the library by its text and a preset by its name, so it can run after `demo:seed` (which writes the library of the verified company) and again.
 */
final readonly class DemoPresets
{
    private const int PAGE = 100;

    public function __construct(
        private ListAdminUsers $users,
        private DemoActors $actors,
        private DemoReferences $references,
        private ListScreeningQuestions $listQuestions,
        private CreateScreeningQuestion $createQuestion,
        private ListScreeningPresets $listPresets,
        private CreateScreeningPreset $createPreset,
    ) {}

    /**
     * @return array{questions: int, presets: int} what this run made
     *
     * @throws StateConflict `demo-presets-need-demo-data` when a company admin of the demo does not exist
     */
    public function build(): array
    {
        $made = ['questions' => 0, 'presets' => 0];
        $levels = $this->levels();

        foreach (DemoPresetContent::presets() as $tenant => $presets) {
            $admin = $this->actors->member($this->userId(DemoCast::COMPANIES[$tenant]['admin_email']));
            $library = $this->library($admin, $tenant, $made);
            $existing = array_map(static fn ($preset): string => mb_strtolower($preset->name), $this->listPresets->execute($admin, null, null, new PageRequest(1, self::PAGE))->items);

            foreach ($presets as $preset) {
                if (in_array(mb_strtolower($preset['name']), $existing, true)) {
                    continue;
                }

                $items = array_map(fn (array $item): array => [
                    'question_id' => (int) ($library[$item[0]] ?? throw new LogicException("The question {$item[0]} of preset {$preset['name']} is not in the demo content."))->id,
                    'required' => $item[1],
                    'must_have' => $item[2],
                    'ideal' => $item[2] ? $this->ideal($tenant, $item[0], $library[$item[0]]) : null,
                ], $preset['items']);
                $this->createPreset->execute($admin, $preset['name'], $preset['level'] === null ? null : $levels[$preset['level']], $items);
                $made['presets']++;
            }
        }

        return $made;
    }

    /**
     * The library of the company by question key: the questions of the content that are in it by their text, and the extra ones made now.
     *
     * @param  array{questions: int, presets: int}  $made
     * @return array<string, ScreeningQuestion>
     */
    private function library(Actor $admin, string $tenant, array &$made): array
    {
        $byText = [];

        foreach ($this->listQuestions->execute($admin, false, null, new PageRequest(1, self::PAGE))->items as $question) {
            $byText[$question->text] = $question;
        }

        $content = $tenant === DemoCast::SINAR ? DemoScreeningContent::questions() : [];
        $library = [];

        foreach ([...$content, ...DemoPresetContent::extraQuestions()[$tenant]] as $key => $question) {
            if (! isset($byText[$question['text']])) {
                $byText[$question['text']] = $this->createQuestion->execute($admin, $question['type'], $question['text'], $question['labels']);
                $made['questions']++;
            }

            $library[$key] = $byText[$question['text']];
        }

        return $library;
    }

    /** @return array<string, mixed> */
    private function ideal(string $tenant, string $key, ScreeningQuestion $question): array
    {
        $ideal = DemoScreeningContent::ideals()[$key] ?? DemoPresetContent::extraIdeals()[$tenant][$key] ?? throw new LogicException("The must-have question {$key} has no ideal answer.");

        return match ($question->type) {
            QuestionType::YesNo => ['yes' => $ideal['yes'] ?? true],
            QuestionType::Number => ['min' => $ideal['min'] ?? 0],
            default => ['option_ids' => $this->optionIds($question, $ideal['labels'] ?? [])],
        };
    }

    /**
     * @param  list<string>  $labels
     * @return list<int>
     */
    private function optionIds(ScreeningQuestion $question, array $labels): array
    {
        $ids = [];

        foreach ($question->options as $option) {
            if (in_array($option->label, $labels, true)) {
                $ids[] = $option->id;
            }
        }

        return $ids;
    }

    /**
     * The ids of the job levels of the reference data in their order: junior, middle, senior.
     *
     * @return array{junior: int, middle: int, senior: int}
     */
    private function levels(): array
    {
        [$junior, $middle, $senior] = array_pad($this->references->ids('job-levels', 3), 3, $this->references->id('job-levels'));

        return ['junior' => $junior, 'middle' => $middle, 'senior' => $senior];
    }

    private function userId(string $email): int
    {
        foreach ($this->users->execute($email, new PageRequest(1, 5))->items as $view) {
            if (mb_strtolower($view->user->email()) === mb_strtolower($email)) {
                return (int) $view->user->id;
            }
        }

        throw new StateConflict('The demo account :email does not exist. Run demo:seed first.', 'demo-presets-need-demo-data', parameters: ['email' => $email]);
    }
}
