<?php

namespace Src\Recruitment\Domain\JobManagement;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Sisa kuota paket perusahaan (`user_plans`). */
final class PlanQuota
{
    public function __construct(
        public readonly int $id,
        private int $jobLimit,
        private int $featuredLimit,
        private int $highlightLimit,
    ) {}

    public function jobLimit(): int
    {
        return $this->jobLimit;
    }

    public function featuredLimit(): int
    {
        return $this->featuredLimit;
    }

    public function highlightLimit(): int
    {
        return $this->highlightLimit;
    }

    public function consumeJob(): void
    {
        if ($this->jobLimit < 1) {
            throw new BusinessRuleViolation(JobMessages::get('you_have_reached_your_plan_limit_please_upgrade_your_plan'));
        }
        $this->jobLimit--;
    }

    public function consumeBadge(PromotionBadge $badge): void
    {
        if ($badge === PromotionBadge::Featured) {
            if ($this->featuredLimit < 1) {
                throw new BusinessRuleViolation(JobMessages::get('you_have_no_featured_job_limit'));
            }
            $this->featuredLimit--;

            return;
        }

        if ($this->highlightLimit < 1) {
            throw new BusinessRuleViolation(JobMessages::get('you_have_no_highlight_job_limit'));
        }
        $this->highlightLimit--;
    }

    /**
     * Saat menduplikasi lowongan: badge asli dipertahankan hanya bila kuotanya masih ada
     * (dan kuota itu dipakai). Mengembalikan [featured, highlight].
     *
     * @return array{0: bool, 1: bool}
     */
    public function inheritBadges(bool $featured, bool $highlight): array
    {
        $keepFeatured = $featured && $this->featuredLimit > 0;
        if ($keepFeatured) {
            $this->featuredLimit--;
        }

        $keepHighlight = $highlight && $this->highlightLimit > 0;
        if ($keepHighlight) {
            $this->highlightLimit--;
        }

        return [$keepFeatured, $keepHighlight];
    }
}
