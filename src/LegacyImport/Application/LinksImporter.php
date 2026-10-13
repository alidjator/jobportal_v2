<?php

namespace Src\LegacyImport\Application;

use Src\JobSeeker\Domain\LinkType;
use Src\Shared\Application\TransactionManager;
use stdClass;

/**
 * Imports the links of a job seeker: the candidate's `website` (kind `website`) and the `social_links` of the account, mapped to the new kinds (twitter to `x`;
 * facebook, youtube, pinterest and reddit to `other`). The addresses are kept as they are, with no check of the new link rules (https, own domain, no shortener); a kind that is not known becomes `other`;
 * only an empty address or one longer than the column is skipped. No limit on their number. Only for job seekers whose profile was imported.
 */
final readonly class LinksImporter implements Importer
{
    public const string ENTITY = 'links';

    /** The id-map entities: a legacy `social_links` id, and a legacy candidate id for the website. */
    public const string SOCIAL = 'link';

    public const string WEBSITE = 'link.website';

    private const int URL_MAX = 255;

    private const array TYPES = [
        'facebook' => LinkType::Other, 'twitter' => LinkType::X, 'instagram' => LinkType::Instagram, 'youtube' => LinkType::Other, 'linkedin' => LinkType::LinkedIn,
        'pinterest' => LinkType::Other, 'reddit' => LinkType::Other, 'github' => LinkType::GitHub, 'other' => LinkType::Other,
    ];

    public function __construct(
        private LegacySource $legacy,
        private JobSeekerWriter $writer,
        private TransactionManager $transactions,
    ) {}

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [ProfilesImporter::ENTITY];
    }

    public function run(ImportContext $context): void
    {
        $candidates = $this->legacy->all('candidates', ['id', 'user_id', 'website']);
        $social = $this->legacy->all('social_links', ['id', 'user_id', 'social_media', 'url']);

        $candidateOfUser = [];

        foreach ($candidates as $candidate) {
            $candidateOfUser[(int) $candidate->user_id] ??= (int) $candidate->id;
        }

        $users = $context->idMap->findMany(AccountsImporter::USER, array_keys($candidateOfUser));
        $profiles = $context->idMap->findMany(ProfilesImporter::PROFILE, array_values($candidateOfUser));
        $doneSocial = $context->idMap->findMany(self::SOCIAL, array_map(static fn (stdClass $row): int => (int) $row->id, $social));
        $doneWebsite = $context->idMap->findMany(self::WEBSITE, array_values($candidateOfUser));

        /** @var array<int, list<array{entity: string, legacyId: int, type: LinkType|null, url: string}>> $byUser */
        $byUser = [];

        foreach ($candidates as $candidate) {
            if (LegacyText::clean($candidate->website) !== null) {
                $byUser[(int) $candidate->user_id][] = ['entity' => self::WEBSITE, 'legacyId' => (int) $candidate->id, 'type' => LinkType::Website, 'url' => (string) $candidate->website];
            }
        }

        foreach ($social as $link) {
            $byUser[(int) $link->user_id][] = ['entity' => self::SOCIAL, 'legacyId' => (int) $link->id, 'type' => self::TYPES[$link->social_media] ?? null, 'url' => (string) $link->url];
        }

        $this->transactions->run(function () use ($byUser, $candidateOfUser, $users, $profiles, $doneSocial, $doneWebsite, $context): void {
            foreach ($byUser as $legacyUser => $links) {
                $candidate = $candidateOfUser[$legacyUser] ?? null;
                $userId = $candidate === null ? null : ($users[$legacyUser] ?? null);
                foreach ($links as $link) {
                    $this->importLink($link, $candidate, $userId, isset($profiles[(int) $candidate]), $link['entity'] === self::WEBSITE ? isset($doneWebsite[$link['legacyId']]) : isset($doneSocial[$link['legacyId']]), $context);
                }
            }
        });
    }

    /**
     * @param  array{entity: string, legacyId: int, type: LinkType|null, url: string}  $link
     * @param  array<string, int>  $perType
     */
    private function importLink(array $link, ?int $candidate, ?int $userId, bool $hasProfile, bool $alreadyImported, ImportContext $context): void
    {
        $report = $context->report;
        $report->read(self::ENTITY);

        if ($alreadyImported) {
            $report->note(self::ENTITY, 'already-imported');

            return;
        }

        $type = $link['type'] ?? LinkType::Other;

        $reason = match (true) {
            $candidate === null => 'not-a-job-seeker',
            $userId === null || ! $hasProfile => 'profile-not-imported',
            default => null,
        };

        if ($reason !== null) {
            $report->skipped(self::ENTITY, $link['legacyId'], $reason);

            return;
        }

        $url = trim($link['url']);

        if ($url === '' || mb_strlen($url) > self::URL_MAX) {
            $report->skipped(self::ENTITY, $link['legacyId'], 'link-invalid');

            return;
        }

        if ($context->write) {
            $newId = $this->writer->insert('link', ['user_id' => $userId, 'type' => $type->value, 'url' => $url]);
            $context->idMap->remember($link['entity'], $link['legacyId'], $newId, $context->clock->now());
        }

        $report->imported(self::ENTITY);
    }
}
