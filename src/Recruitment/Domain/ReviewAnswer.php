<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** The answer of one reviewer about one application of a review request. A decline needs a reason. */
final readonly class ReviewAnswer
{
    public const int REASON_MAX = 1000;

    public function __construct(
        public int $requestId,
        public int $applicationId,
        public int $reviewerId,
        public ReviewDecision $decision,
        public ?string $reason,
        public DateTimeImmutable $answeredAt,
    ) {}

    /**
     * @throws BusinessRuleViolation `reason-required`
     */
    public static function give(int $requestId, int $applicationId, int $reviewerId, ReviewDecision $decision, ?string $reason, DateTimeImmutable $now): self
    {
        $reason = $reason === null || trim($reason) === '' ? null : trim($reason);

        if ($decision === ReviewDecision::Decline && $reason === null) {
            $message = 'Give a reason when you do not want to go on with a candidate.';

            throw new BusinessRuleViolation($message, 'reason-required', extensions: ['errors' => ['reason' => [$message]]]);
        }

        return new self($requestId, $applicationId, $reviewerId, $decision, $reason, $now);
    }
}
