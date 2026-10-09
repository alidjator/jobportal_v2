<?php

namespace Src\JobSeeker\Application\Cv;

use Src\JobSeeker\Domain\CvInclusion;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Applicant\GeneratedCvSpec;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Makes the CV of a job seeker from their latest profile. Used by the download and by an application that attaches a generated CV. The CV is never stored here:
 * the caller gets the bytes. The hourly limit is checked first and one record is added once the PDF exists.
 */
final readonly class GenerateCv
{
    private const int NAME_MAX = 100;

    public function __construct(
        private CvTemplates $templates,
        private CvQuota $quota,
        private ApplicantContacts $contacts,
        private CvDocumentBuilder $documents,
        private CvRenderer $renderer,
    ) {}

    /**
     * @throws EntityNotFound the account is missing or was anonymised
     * @throws BusinessRuleViolation `template-unavailable` or `invalid-cv`
     * @throws TooManyAttempts `cv-limit`
     */
    public function execute(int $userId, GeneratedCvSpec $spec): GeneratedCv
    {
        $template = $this->templates->resolve($spec->template);
        $contact = $this->contacts->contact($userId) ?? throw EntityNotFound::of('Account', $userId);
        $language = $spec->language ?? (in_array($contact->locale, CvLabels::LANGUAGES, true) ? $contact->locale : 'en');

        if (! in_array($language, CvLabels::LANGUAGES, true)) {
            throw new BusinessRuleViolation('The language of a CV is id or en.', 'invalid-cv', extensions: ['errors' => ['language' => ['The language of a CV is id or en.']]]);
        }

        $include = [];

        foreach ($spec->include as $key) {
            $include[] = CvInclusion::tryFrom($key) ?? throw new BusinessRuleViolation(
                "A CV cannot include {$key}.", 'invalid-cv', extensions: ['errors' => ['include' => ["A CV cannot include {$key}."]]],
            );
        }

        $this->quota->assertRoom($userId);
        $pdf = $this->renderer->render($this->documents->build($userId, $contact, $template, $language, $include));
        $this->quota->record($userId);

        return new GeneratedCv($this->fileName($contact->name), $pdf);
    }

    /** "CV <name>.pdf", with the name cut of control characters and path separators. */
    private function fileName(string $name): string
    {
        $clean = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\"%]+/u', ' ', $name));

        return 'CV '.mb_substr($clean === '' ? 'Resume' : $clean, 0, self::NAME_MAX).'.pdf';
    }
}
