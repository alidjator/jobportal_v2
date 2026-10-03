<?php

namespace Src\Company\Domain;

interface BookmarkNotifier
{
    /**
     * Beri tahu perusahaan (selalu) dan kandidat (bila `$notifyCandidate`) bahwa bookmark dibuat.
     *
     * @param  array{id:int,name:string,username:string}  $company
     * @param  array{id:int,name:string,username:string}  $candidate
     */
    public function bookmarked(array $company, array $candidate, bool $notifyCandidate): void;
}
