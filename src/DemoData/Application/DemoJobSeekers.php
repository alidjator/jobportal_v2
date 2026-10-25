<?php

namespace Src\DemoData\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\DemoData\Domain\DemoPdf;
use Src\Identity\Application\Auth\RegisterJobSeeker;
use Src\Identity\Application\Auth\RegisterJobSeekerCommand;
use Src\Identity\Application\Auth\SignOutEverywhere;
use Src\Identity\Application\Auth\VerifyEmail;
use Src\JobSeeker\Application\AddMyExperience;
use Src\JobSeeker\Application\ReplaceMyLanguages;
use Src\JobSeeker\Application\ReplaceMyLinks;
use Src\JobSeeker\Application\ReplaceMySkills;
use Src\JobSeeker\Application\UpdateMyProfile;
use Src\JobSeeker\Application\UploadMyResume;
use Src\JobSeeker\Domain\LanguageEntry;
use Src\JobSeeker\Domain\LanguageProficiency;
use Src\JobSeeker\Domain\LinkType;
use Src\JobSeeker\Domain\WorkExperience;
use Src\Shared\Application\Actor;

/**
 * The job seekers: each registers, verifies the email with the code the registration sent, completes the profile (phone, location, one past job, three
 * skills, two languages and a LinkedIn link, a GitHub link for every second one) and uploads one resume, so every one of them can apply with a profile the
 * companies see in full (the profile is frozen into the application). The session the registration opened is ended.
 */
final readonly class DemoJobSeekers
{
    public function __construct(
        private RegisterJobSeeker $register,
        private VerifyEmail $verifyEmail,
        private SignOutEverywhere $signOut,
        private UpdateMyProfile $updateProfile,
        private AddMyExperience $addExperience,
        private ReplaceMySkills $replaceSkills,
        private ReplaceMyLanguages $replaceLanguages,
        private ReplaceMyLinks $replaceLinks,
        private UploadMyResume $uploadResume,
        private DemoReferences $references,
        private DemoInbox $inbox,
    ) {}

    /** @return list<array{user_id: int, resume_id: int}> in the order of `DemoCast::jobSeekers()` */
    public function build(string $password): array
    {
        $place = $this->references->place();
        $skills = $this->references->ids('skills', 5);
        $languages = $this->references->ids('languages', 2);
        $utc = new DateTimeZone('UTC');
        $seekers = [];

        foreach (DemoCast::jobSeekers() as $index => $seeker) {
            $registered = $this->register->execute(new RegisterJobSeekerCommand(
                $seeker['name'], $seeker['email'], $password, DemoCast::LANGUAGE, null, DemoCast::USER_AGENT, null, DemoCast::USER_AGENT,
            ));
            $actor = Actor::jobSeeker((int) $registered->user->id);
            $this->verifyEmail->execute($actor, $this->inbox->verificationCode($seeker['email']));

            $this->updateProfile->execute($actor, [
                'phone' => sprintf('08120000%04d', $index + 1),
                'birthDate' => new DateTimeImmutable($seeker['birth_date'], $utc),
                'gender' => $seeker['gender'],
                'headline' => $seeker['headline'],
                ...$place,
            ]);
            $this->addExperience->execute($actor, new WorkExperience(
                0, $seeker['last_job'], 'PT Contoh Karya Mandiri', null, null, new DateTimeImmutable('2021-02-01', $utc), new DateTimeImmutable('2024-06-01', $utc), false, null,
            ));
            $this->replaceSkills->execute($actor, array_slice($skills, $index % 3, 3));
            $this->replaceLanguages->execute($actor, array_values(array_filter([
                new LanguageEntry($languages[0], LanguageProficiency::Native),
                isset($languages[1]) ? new LanguageEntry($languages[1], LanguageProficiency::Conversational) : null,
            ])));
            $this->replaceLinks->execute($actor, $this->links($seeker['name'], $index));
            $resume = $this->uploadResume->execute($actor, DemoPdf::make([
                $seeker['name'], $seeker['headline'], $seeker['email'], 'Demo resume: fictitious person, not a real record.',
            ]), 'CV '.$seeker['name'].'.pdf');

            $this->signOut->execute($actor, null, DemoCast::USER_AGENT);
            $seekers[] = ['user_id' => (int) $registered->user->id, 'resume_id' => (int) $resume->id];
        }

        return $seekers;
    }

    /**
     * @return list<array{type: LinkType, url: string}>
     */
    private function links(string $name, int $index): array
    {
        $slug = strtolower(str_replace(' ', '-', $name));
        $links = [['type' => LinkType::LinkedIn, 'url' => "https://www.linkedin.com/in/{$slug}"]];

        if ($index % 2 === 0) {
            $links[] = ['type' => LinkType::GitHub, 'url' => "https://github.com/{$slug}"];
        }

        return $links;
    }
}
