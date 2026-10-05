<?php

namespace Src\Company\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** The public URL name of a company: 3 to 60 lower case letters, digits and hyphens, never starting or ending with a hyphen or holding two in a row. */
final readonly class CompanySlug
{
    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 60;

    /** Names that would clash with the API's own words or look official. */
    public const array RESERVED = [
        'admin', 'api', 'auth', 'login', 'register', 'company', 'companies', 'jobs', 'job', 'me',
        'search', 'about', 'help', 'faq', 'terms', 'privacy', 'support', 'www',
    ];

    private function __construct(public string $value) {}

    /**
     * @throws BusinessRuleViolation `invalid-slug` for a wrong format, `slug-reserved` for a reserved name
     */
    public static function fromInput(string $input): self
    {
        $value = trim($input);

        $length = strlen($value);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            throw new BusinessRuleViolation(
                'The slug must be 3 to 60 characters: lower case letters, digits and single hyphens, not starting or ending with a hyphen.',
                'invalid-slug',
            );
        }

        if (in_array($value, self::RESERVED, true)) {
            throw new BusinessRuleViolation('This slug is reserved.', 'slug-reserved');
        }

        return new self($value);
    }
}
