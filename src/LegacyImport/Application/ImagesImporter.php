<?php

namespace Src\LegacyImport\Application;

use Src\Company\Application\Images\CompanyImageKind;
use Src\Identity\Application\IdentitySettings;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Images\ImageProcessor;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use stdClass;

/**
 * Carries the legacy profile photos (`candidates.photo`, else `users.image`), company logos and company banners through the new image processor: the file is
 * read from the legacy project folder, checked against the size limit of the new upload, decoded and encoded again (content check, no metadata), and stored
 * under a random name on the private disk. The default image (`default.png`) means no image. A file that is missing, too large or broken is skipped with
 * its reason. Nothing is overwritten: an account or company that already has an image keeps it. A dry run reads and processes the image but stores nothing.
 */
final readonly class ImagesImporter implements Importer
{
    public const string ENTITY = 'images';

    public const string AVATAR = 'images.avatar';

    public const string LOGO = 'images.logo';

    public const string BANNER = 'images.banner';

    private const int BATCH = 100;

    private const int AVATAR_SIDE = 512;

    private const string AVATAR_DIRECTORY = 'identity/avatars';

    public function __construct(
        private LegacySource $legacy,
        private LegacyFiles $files,
        private ImageProcessor $images,
        private FileStorage $storage,
        private AccountWriter $accounts,
        private CompanyWriter $companies,
        private Settings $settings,
        private TransactionManager $transactions,
    ) {}

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [AccountsImporter::ENTITY, CompaniesImporter::ENTITY];
    }

    public function run(ImportContext $context): void
    {
        $this->avatars($context);
        $this->companyImages($context);
    }

    private function avatars(ImportContext $context): void
    {
        $this->legacy->chunk('users', self::BATCH, function (array $users) use ($context): void {
            $ids = array_map(static fn (stdClass $user): int => (int) $user->id, $users);
            $accounts = $context->idMap->findMany(AccountsImporter::USER, $ids);
            $done = $context->idMap->findMany(self::AVATAR, $ids);
            $photos = [];

            foreach ($this->legacy->whereIn('candidates', 'user_id', $ids, ['user_id', 'photo']) as $candidate) {
                $photos[(int) $candidate->user_id] = $candidate->photo;
            }

            foreach ($users as $user) {
                $legacyId = (int) $user->id;
                $path = $this->imagePath($photos[$legacyId] ?? null) ?? $this->imagePath($user->image);

                if ($path === null || ! isset($accounts[$legacyId]) || isset($done[$legacyId])) {
                    continue;
                }

                $newId = $accounts[$legacyId];
                $this->carry($context, self::AVATAR, $legacyId, $path, $this->settings->integer(IdentitySettings::AVATAR_MAX_SIZE_KB), self::AVATAR_SIDE, self::AVATAR_SIDE, self::AVATAR_DIRECTORY,
                    fn (string $stored): bool => $this->accounts->setAvatarIfEmpty($newId, $stored), $newId);
            }
        });
    }

    private function companyImages(ImportContext $context): void
    {
        $this->legacy->chunk('companies', self::BATCH, function (array $companies) use ($context): void {
            $ids = array_map(static fn (stdClass $company): int => (int) $company->id, $companies);
            $mapped = $context->idMap->findMany(CompaniesImporter::COMPANY, $ids);

            foreach ([[self::LOGO, 'logo', CompanyImageKind::Logo], [self::BANNER, 'banner', CompanyImageKind::Banner]] as [$entity, $column, $kind]) {
                $done = $context->idMap->findMany($entity, $ids);

                foreach ($companies as $company) {
                    $legacyId = (int) $company->id;
                    $path = $this->imagePath($company->{$column});

                    if ($path === null || ! isset($mapped[$legacyId]) || isset($done[$legacyId])) {
                        continue;
                    }

                    $newId = $mapped[$legacyId];
                    $this->carry($context, $entity, $legacyId, $path, $this->settings->integer($kind->maxSizeSetting()), $kind->maxWidth(), $kind->maxHeight(), $kind->directory(),
                        fn (string $stored): bool => $this->companies->setImageIfEmpty($newId, $column, $stored), $newId);
                }
            }
        });
    }

    /**
     * Reads, checks and processes one image and (when writing) stores it and attaches it.
     *
     * @param  callable(string): bool  $attach  attaches the stored path; false when the owner already had an image
     */
    private function carry(ImportContext $context, string $entity, int $legacyId, string $path, int $maxKb, int $maxWidth, int $maxHeight, string $directory, callable $attach, int $newId): void
    {
        $report = $context->report;
        $report->read($entity);

        $contents = $this->files->isConfigured() ? $this->files->read($path) : null;

        $reason = match (true) {
            ! $this->files->isConfigured() => 'files-root-not-configured',
            $contents === null => 'file-missing',
            strlen($contents) > $maxKb * 1024 => 'too-large',
            default => null,
        };

        if ($reason !== null) {
            $report->skipped($entity, $legacyId, $reason);

            return;
        }

        try {
            $image = $this->images->process($contents, $maxWidth, $maxHeight);
        } catch (BusinessRuleViolation) {
            $report->skipped($entity, $legacyId, 'invalid-image');

            return;
        }

        if (! $context->write) {
            $report->imported($entity);

            return;
        }

        $stored = $directory.'/'.bin2hex(random_bytes(16)).'.'.$image->extension;
        $this->storage->put($stored, $image->contents);

        try {
            $this->transactions->run(function () use ($attach, $stored, $context, $entity, $legacyId, $newId): void {
                if ($attach($stored)) {
                    $context->idMap->remember($entity, $legacyId, $newId, $context->clock->now());
                } else {
                    $context->report->note($entity, 'already-has-image');
                    $this->storage->delete($stored);
                }
            });
        } catch (\Throwable $e) {
            $this->storage->delete($stored);

            throw $e;
        }

        $report->imported($entity);
    }

    /** The legacy path, or null when there is no image (empty, or the default picture). */
    private function imagePath(mixed $value): ?string
    {
        $value = LegacyText::clean($value === null ? null : (string) $value);

        return $value === null || basename(str_replace('\\', '/', $value)) === 'default.png' ? null : $value;
    }
}
