<?php

namespace Src\Company\Domain;

/**
 * The default texts a company starts its messages with: the note of an interview, of a test invitation, of an offer and of a hire, and the message of the
 * "application received" email. Only a start for the recruiter: what is sent is the note the recruiter saved, so changing a default never touches a message already sent.
 */
final readonly class MessageDefaults
{
    public const array SCENARIOS = ['interview', 'assessment', 'offer', 'applied', 'hired'];

    /** The longest default of a scenario, in characters: an offer note may be twice as long as the others. */
    public const array MAX_LENGTH = ['interview' => 1000, 'assessment' => 1000, 'offer' => 2000, 'applied' => 1000, 'hired' => 1000];

    public function __construct(
        public ?string $interview = null,
        public ?string $assessment = null,
        public ?string $offer = null,
        public ?string $applied = null,
        public ?string $hired = null,
    ) {}

    /**
     * The defaults with these scenarios replaced; a blank text clears one.
     *
     * @param  array<string, ?string>  $changes  keyed by scenario
     */
    public function with(array $changes): self
    {
        $values = $this->toArray();

        foreach ($changes as $scenario => $text) {
            $text = $text === null ? null : trim($text);
            $values[$scenario] = $text === '' ? null : $text;
        }

        return new self($values['interview'], $values['assessment'], $values['offer'], $values['applied'], $values['hired']);
    }

    /** @return array{interview: ?string, assessment: ?string, offer: ?string, applied: ?string, hired: ?string} */
    public function toArray(): array
    {
        return ['interview' => $this->interview, 'assessment' => $this->assessment, 'offer' => $this->offer, 'applied' => $this->applied, 'hired' => $this->hired];
    }
}
