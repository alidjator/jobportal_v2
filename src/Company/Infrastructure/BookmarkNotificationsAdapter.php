<?php

namespace Src\Company\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Src\Company\Domain\BookmarkNotifier;
use Src\Company\Infrastructure\Notifications\CandidateBookmarkedNotification;
use Src\Company\Infrastructure\Persistence\Eloquent\NotifiableUser;

/** Notifikasi database Laravel, format sama dengan `CandidateBookmarkNotification` lama. */
class BookmarkNotificationsAdapter implements BookmarkNotifier
{
    public function bookmarked(array $company, array $candidate, bool $notifyCandidate): void
    {
        $notification = new CandidateBookmarkedNotification($company, $candidate);

        if ($notifyCandidate) {
            Notification::send(NotifiableUser::query()->find($this->candidateUserId($candidate['id'])), $notification);
        }

        Notification::send(NotifiableUser::query()->find($company['id']), $notification);
    }

    private function candidateUserId(int $candidateId): ?int
    {
        // Tabel `candidates` (CandidateProfile), read-only: pemilik akun kandidat.
        return DB::table('candidates')->where('id', $candidateId)->value('user_id');
    }
}
