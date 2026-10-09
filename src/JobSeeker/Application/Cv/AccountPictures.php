<?php

namespace Src\JobSeeker\Application\Cv;

/** Finds the profile picture of an account (Identity owns it; the file is on the private disk). */
interface AccountPictures
{
    /** The storage path of the picture, or null when the account has none. */
    public function pathOf(int $userId): ?string;
}
