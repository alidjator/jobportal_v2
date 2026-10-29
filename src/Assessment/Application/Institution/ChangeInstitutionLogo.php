<?php

namespace Src\Assessment\Application\Institution;

use Src\Assessment\Application\Team\TeamAccess;
use Src\Assessment\Domain\Provider;
use Src\Assessment\Domain\ProviderRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Throwable;

/** A lead puts, replaces or takes off the logo of their institution. The new file is stored first and the institution changed next; the old file is deleted last. */
final readonly class ChangeInstitutionLogo
{
    public const string DIRECTORY = 'assessment/institution-logos';

    public const int MAX_KILOBYTES = 2048;

    private const int MAX_WIDTH = 800;

    private const int MAX_HEIGHT = 400;

    public function __construct(
        private TeamAccess $access,
        private ProviderRepository $providers,
        private PrivateImages $images,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-lead` and the refusals of `TeamAccess`
     * @throws BusinessRuleViolation `file-too-large` or `invalid-image`
     */
    public function put(Actor $actor, string $contents): Provider
    {
        $lead = $this->access->lead($actor);
        $path = $this->images->store(self::DIRECTORY, $contents, self::MAX_KILOBYTES, self::MAX_WIDTH, self::MAX_HEIGHT);

        try {
            [$provider, $previous] = $this->swap($lead->providerId, $path);
        } catch (Throwable $e) {
            $this->images->deleteQuietly($path);

            throw $e;
        }

        $this->images->deleteQuietly($previous);

        return $provider;
    }

    /** @throws AccessDenied `not-a-lead` and the refusals of `TeamAccess` */
    public function remove(Actor $actor): Provider
    {
        [$provider, $previous] = $this->swap($this->access->lead($actor)->providerId, null);
        $this->images->deleteQuietly($previous);

        return $provider;
    }

    /** @return array{0: Provider, 1: string|null} the changed provider and the path of the logo it replaced */
    private function swap(int $providerId, ?string $path): array
    {
        return $this->transactions->run(function () use ($providerId, $path): array {
            $provider = $this->providers->findById($providerId, forUpdate: true) ?? throw EntityNotFound::of('Provider', $providerId);
            $previous = $provider->identity()->logoPath;
            $provider->changeIdentity($provider->identity()->withLogo($path));

            return [$this->providers->save($provider), $previous];
        });
    }
}
