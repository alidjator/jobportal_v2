<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Register\RegisterItem;
use Src\Assessment\Domain\Recommendation;

/**
 * A line of the report register. There is no candidate in it (not the name, not the company's job): the register is the provider's record of the reports it issued.
 *
 * @property RegisterItem $resource
 */
final class RegisterItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $entry = $this->resource->entry;

        return [
            'id' => $entry->reportId,
            'report_number' => $entry->number,
            /** 0 for the first issue; 1, 2, ... for each revision (the number then ends with -R1, -R2). */
            'revision' => $entry->revision,
            'signed_at' => $entry->signedAt->format(DateTimeInterface::ATOM),
            'psychologist_id' => $entry->psychologistId,
            'signer_name' => $entry->signerName,
            'test_id' => $entry->testId,
            'test_title' => $entry->testTitle,
            'company_name' => $this->resource->companyName,
            'auto_issued' => $entry->autoIssued,
            /** True for a report signed by hand and for an automatic one a psychologist reviewed. */
            'reviewed' => $entry->reviewed(),
            'recommendation' => $entry->recommendation instanceof Recommendation ? $entry->recommendation->value : null,
            /** True once the candidate's account was anonymised: the text is gone; the number, the signer and the time stay. */
            'erased' => $entry->erased,
        ];
    }
}
