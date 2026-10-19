<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;

/**
 * Writes the iCalendar (RFC 5545) file of an interview (`docs/design/phase-8-interview-calendar.md` section 3). One interview chain is one event: the UID stays and the
 * sequence rises with every change, so a calendar app moves or cancels the event it has. Times are UTC. The description never holds a join token; an external
 * meeting link the recruiter set is included, as in the email. `$pageUrlTemplate` is the job seeker's page of the application with `{application_id}` in it (empty: no link);
 * `$teamPageUrlTemplate` is the company team's page, used by the files made through `forTeam()` (the panel's, the downloaded one and the team's cancellation).
 */
final readonly class InterviewCalendar
{
    public const string REQUEST = 'REQUEST';

    public const string CANCEL = 'CANCEL';

    public const string PUBLISH = 'PUBLISH';

    private const int FOLD = 75;

    public function __construct(private string $organizerAddress, private ?string $pageUrlTemplate = null, private ?string $teamPageUrlTemplate = null) {}

    /** The same calendar writing the company team's page in place of the job seeker's. */
    public function forTeam(): self
    {
        return new self($this->organizerAddress, $this->teamPageUrlTemplate, $this->teamPageUrlTemplate);
    }

    /**
     * @param  ?array{name: string, email: string}  $attendee  the job seeker; left out of the file the team downloads
     */
    public function write(Interview $interview, string $method, string $jobTitle, string $companyName, ?array $attendee, DateTimeImmutable $now): string
    {
        $page = $this->page($interview);
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ONESIS//Interview//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:'.$method,
            'BEGIN:VEVENT',
            'UID:'.$interview->calendarUid.'@onesis',
            'SEQUENCE:'.$interview->calendarSequence,
            'DTSTAMP:'.$this->utc($now),
            'DTSTART:'.$this->utc($interview->startsAt),
            'DTEND:'.$this->utc($interview->endsAt),
            'SUMMARY:'.$this->escape("Interview: {$jobTitle} - {$companyName}"),
            'LOCATION:'.$this->escape($this->location($interview)),
            'DESCRIPTION:'.$this->escape($this->description($interview, $page)),
            'ORGANIZER;CN='.$this->param($companyName).':mailto:'.$this->organizerAddress,
        ];

        if ($attendee !== null) {
            $lines[] = 'ATTENDEE;CN='.$this->param($attendee['name']).';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=FALSE:mailto:'.$attendee['email'];
        }

        if ($page !== null) {
            $lines[] = 'URL:'.$page;
        }

        $lines[] = 'STATUS:'.($method === self::CANCEL ? 'CANCELLED' : 'CONFIRMED');
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    private function location(Interview $interview): string
    {
        return match ($interview->format) {
            InterviewFormat::Onsite => (string) $interview->location,
            InterviewFormat::Online => 'Online',
            InterviewFormat::Video => 'Video di platform',
            InterviewFormat::Voice => 'Telepon',
        };
    }

    private function description(Interview $interview, ?string $page): string
    {
        $parts = ['Format: '.match ($interview->format) {
            InterviewFormat::Online => 'Online',
            InterviewFormat::Onsite => 'Onsite',
            InterviewFormat::Video => 'Video in the platform',
            InterviewFormat::Voice => 'Phone call',
        }];

        if ($interview->format === InterviewFormat::Online && $interview->meetingUrl !== null) {
            $parts[] = 'Link: '.$interview->meetingUrl;
        }

        if ($interview->note !== null) {
            $parts[] = 'Note: '.$interview->note;
        }

        if ($page !== null) {
            $parts[] = 'Details: '.$page;
        }

        return implode("\n", $parts);
    }

    private function page(Interview $interview): ?string
    {
        return $this->pageUrlTemplate === null || $this->pageUrlTemplate === '' ? null : str_replace('{application_id}', (string) $interview->applicationId, $this->pageUrlTemplate);
    }

    private function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /** A TEXT value: backslash, semicolon, comma and line breaks are escaped. */
    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /** A parameter value in double quotes, which cannot hold a double quote, a control character or a line break. */
    private function param(string $text): string
    {
        return '"'.str_replace(['"', "\r", "\n"], ["'", ' ', ' '], $text).'"';
    }

    /** Lines of at most 75 octets: a longer line continues on the next, which starts with a space (never inside a multi-byte character). */
    private function fold(string $line): string
    {
        if (strlen($line) <= self::FOLD) {
            return $line;
        }

        $out = '';
        $current = '';
        $limit = self::FOLD;

        foreach (mb_str_split($line) as $character) {
            if (strlen($current) + strlen($character) > $limit) {
                $out .= $current."\r\n ";
                $current = '';
                $limit = self::FOLD - 1;
            }

            $current .= $character;
        }

        return $out.$current;
    }
}
