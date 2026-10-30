<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Application\WorkforceSettings;
use Src\Workforce\Domain\Requisition;
use Src\Workforce\Domain\RequisitionCounters;
use Src\Workforce\Domain\RequisitionNumberFormat;
use Src\Workforce\Domain\RequisitionRepository;

/**
 * The number of a requisition. A company either lets the format of `workforce.requisition_number_format` give it (`auto`, the default) or has the member type it
 * (`workforce.requisition_number_mode` is `manual`): one to 40 characters of plain text, never used twice in the company whatever the letter case. Call it in the transaction that stores the requisition.
 */
final readonly class RequisitionNumbers
{
    public const int TYPED_MAX = 40;

    public function __construct(private Settings $settings, private RequisitionCounters $counters, private RequisitionRepository $requisitions, private Clock $clock) {}

    /**
     * The number of a requisition that is being made: the typed one for a company that types its numbers (required), otherwise the next one of the format, whatever was typed.
     *
     * @throws BusinessRuleViolation `invalid-requisition-field`
     * @throws StateConflict `requisition-number-taken`
     */
    public function forNew(int $companyId, ?string $typed): string
    {
        return $this->typing($companyId) ? $this->typed($companyId, $typed, null) : $this->issue($companyId);
    }

    /**
     * The number of a requisition that is being rewritten: its own, unless the company types its numbers and a different one was sent.
     *
     * @throws BusinessRuleViolation `invalid-requisition-field`
     * @throws StateConflict `requisition-number-taken`
     */
    public function forChange(Requisition $requisition, ?string $typed): ?string
    {
        if ($typed === null || ! $this->typing($requisition->companyId)) {
            return $requisition->number;
        }

        return $this->clean($typed) === $requisition->number ? $requisition->number : $this->typed($requisition->companyId, $typed, $requisition->id);
    }

    /** The next number of the company's format. */
    public function issue(int $companyId): string
    {
        $format = $this->settings->text(WorkforceSettings::REQUISITION_NUMBER_FORMAT, $companyId);
        $format = RequisitionNumberFormat::isValid($format) ? $format : RequisitionNumberFormat::DEFAULT;
        $now = $this->clock->now();

        return RequisitionNumberFormat::render($format, $now, $this->counters->next($companyId, RequisitionNumberFormat::series($format, $now)));
    }

    private function typing(int $companyId): bool
    {
        return $this->settings->text(WorkforceSettings::REQUISITION_NUMBER_MODE, $companyId) === 'manual';
    }

    private function typed(int $companyId, ?string $typed, ?int $exceptId): string
    {
        $number = $this->clean((string) $typed);

        if ($number === '' || mb_strlen($number) > self::TYPED_MAX) {
            $message = 'Type the number of the requisition, at most :max characters.';

            throw new BusinessRuleViolation($message, 'invalid-requisition-field', extensions: ['errors' => ['number' => [$message]]], parameters: ['max' => self::TYPED_MAX]);
        }

        if ($this->requisitions->numberTaken($companyId, $number, $exceptId)) {
            throw new StateConflict('A requisition of this company already has this number.', 'requisition-number-taken');
        }

        return $number;
    }

    /** Plain text on one line: control characters and runs of spaces become one space, the edges are trimmed. */
    private function clean(string $number): string
    {
        return trim((string) preg_replace('/[\p{Cc}\s]+/u', ' ', $number));
    }
}
