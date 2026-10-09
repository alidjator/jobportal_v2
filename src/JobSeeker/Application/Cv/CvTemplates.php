<?php

namespace Src\JobSeeker\Application\Cv;

use Src\JobSeeker\Domain\CvTemplate;
use Src\JobSeeker\Domain\CvTemplateRepository;
use Src\JobSeeker\Domain\CvTemplateState;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Which CV templates exist and which are on. The set is fixed in code; the stored state only switches them on or off and marks the default. A template with no
 * stored state counts as on and not the default. At least one template stays on and the default is always one that is on.
 */
final readonly class CvTemplates
{
    public function __construct(private CvTemplateRepository $states, private TransactionManager $transactions) {}

    /** @return list<CvTemplateState> every template in code order, with the state that applies */
    public function all(): array
    {
        $stored = $this->states->states();
        $states = [];

        foreach (CvTemplate::cases() as $template) {
            $states[] = $stored[$template->value] ?? new CvTemplateState($template, true, false);
        }

        // The default is the stored one that is on; with none (a fresh database), the first template that is on.
        $default = null;

        foreach ($states as $state) {
            if ($state->isDefault && $state->enabled) {
                $default = $state->template;
            }
        }

        $default ??= array_values(array_filter($states, static fn (CvTemplateState $state): bool => $state->enabled))[0]->template ?? CvTemplate::Classic;

        return array_map(static fn (CvTemplateState $state): CvTemplateState => new CvTemplateState($state->template, $state->enabled, $state->template === $default), $states);
    }

    /** @return list<CvTemplateState> the templates that are on, in code order */
    public function enabled(): array
    {
        return array_values(array_filter($this->all(), static fn (CvTemplateState $state): bool => $state->enabled));
    }

    /**
     * The template to use: the one asked for, or the default.
     *
     * @throws BusinessRuleViolation `template-unavailable`
     */
    public function resolve(?string $key): CvTemplate
    {
        foreach ($this->enabled() as $state) {
            if ($key === null ? $state->isDefault : $state->template->value === $key) {
                return $state->template;
            }
        }

        throw new BusinessRuleViolation('This CV template is not available.', 'template-unavailable', extensions: ['errors' => ['template' => ['This CV template is not available.']]]);
    }

    /**
     * Switches a template on or off and/or makes it the default.
     *
     * @throws EntityNotFound
     * @throws StateConflict `cv-template-required` when it would leave no template on, or make a template that is off the default
     */
    public function update(string $key, ?bool $enabled, ?bool $isDefault): CvTemplateState
    {
        $template = CvTemplate::tryFrom($key) ?? throw EntityNotFound::of('CV template', $key);

        return $this->transactions->run(function () use ($template, $enabled, $isDefault): CvTemplateState {
            $this->states->lock();
            $current = array_values(array_filter($this->all(), static fn (CvTemplateState $state): bool => $state->template === $template))[0];
            $on = $enabled ?? $current->enabled;
            // The default cannot be taken away directly: another template has to be made the default.
            $default = $current->isDefault || $isDefault === true;
            $other = $this->firstEnabledExcept($template);

            if ($default && ! $on && $isDefault === true) {
                throw new StateConflict('The default CV template must be switched on.', 'cv-template-required');
            }

            if (! $on && $other === null) {
                throw new StateConflict('At least one CV template must stay switched on.', 'cv-template-required');
            }

            // Switching off the default hands the flag to the first template that stays on.
            if (! $on) {
                $this->states->save(new CvTemplateState($template, false, false));

                if ($current->isDefault) {
                    $this->states->save(new CvTemplateState($other, true, true));
                }

                return new CvTemplateState($template, false, false);
            }

            $this->states->save($state = new CvTemplateState($template, $on, $default));

            return $state;
        });
    }

    private function firstEnabledExcept(CvTemplate $template): ?CvTemplate
    {
        foreach ($this->enabled() as $state) {
            if ($state->template !== $template) {
                return $state->template;
            }
        }

        return null;
    }
}
