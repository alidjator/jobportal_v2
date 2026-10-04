<?php

namespace Src\Identity\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\VerificationCode;
use Src\Identity\Domain\VerificationCodeRepository;
use Src\Identity\Domain\VerificationPurpose;

final class EloquentVerificationCodeRepository implements VerificationCodeRepository
{
    public function findUsable(AccountScope $subjectType, int $subjectId, VerificationPurpose $purpose, DateTimeImmutable $now): ?VerificationCode
    {
        $model = $this->forSubject($subjectType, $subjectId, $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $now)
            ->where('attempts', '<', VerificationCode::MAX_ATTEMPTS)
            ->latest('id')
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function save(VerificationCode $code): VerificationCode
    {
        $model = $code->id === null ? new VerificationCodeModel : VerificationCodeModel::query()->findOrFail($code->id);

        $model->forceFill([
            'subject_type' => $code->subjectType->value,
            'subject_id' => $code->subjectId,
            'purpose' => $code->purpose->value,
            'code_hash' => $code->codeHash,
            'expires_at' => $code->expiresAt,
            'consumed_at' => $code->consumedAt(),
            'attempts' => $code->attempts(),
            'created_at' => $code->createdAt,
        ])->save();

        return $this->toDomain($model);
    }

    public function consumeAllUnconsumed(AccountScope $subjectType, int $subjectId, VerificationPurpose $purpose, DateTimeImmutable $now): void
    {
        $this->forSubject($subjectType, $subjectId, $purpose)->whereNull('consumed_at')->update(['consumed_at' => $now]);
    }

    public function lastIssuedAt(AccountScope $subjectType, int $subjectId, VerificationPurpose $purpose): ?DateTimeImmutable
    {
        $model = $this->forSubject($subjectType, $subjectId, $purpose)->latest('created_at')->latest('id')->first();

        return $model?->created_at->toDateTimeImmutable();
    }

    public function issuedSince(AccountScope $subjectType, int $subjectId, VerificationPurpose $purpose, DateTimeImmutable $since): array
    {
        return $this->forSubject($subjectType, $subjectId, $purpose)
            ->where('created_at', '>', $since)
            ->orderBy('created_at')
            ->get()
            ->map(fn (VerificationCodeModel $model): DateTimeImmutable => $model->created_at->toDateTimeImmutable())
            ->values()
            ->all();
    }

    /** @return Builder<VerificationCodeModel> */
    private function forSubject(AccountScope $subjectType, int $subjectId, VerificationPurpose $purpose): Builder
    {
        return VerificationCodeModel::query()
            ->where('subject_type', $subjectType->value)
            ->where('subject_id', $subjectId)
            ->where('purpose', $purpose->value);
    }

    private function toDomain(VerificationCodeModel $model): VerificationCode
    {
        return new VerificationCode(
            $model->id,
            AccountScope::from($model->subject_type),
            $model->subject_id,
            VerificationPurpose::from($model->purpose),
            $model->code_hash,
            $model->expires_at->toDateTimeImmutable(),
            $model->consumed_at?->toDateTimeImmutable(),
            $model->attempts,
            $model->created_at->toDateTimeImmutable(),
        );
    }
}
