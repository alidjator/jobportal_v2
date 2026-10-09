<?php

namespace Tests\Unit\Dashboard;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Dashboard\DashboardScope;

final class DashboardCardsTest extends TestCase
{
    /** @param  list<DashboardAudience>  $audiences */
    private function card(string $key, array $audiences, ?string $permission = null): DashboardCardProvider
    {
        return new class($key, $audiences, $permission) implements DashboardCardProvider
        {
            /** @param  list<DashboardAudience>  $audiences */
            public function __construct(private string $key, private array $audiences, private ?string $permission) {}

            public function key(): string
            {
                return $this->key;
            }

            public function audiences(): array
            {
                return $this->audiences;
            }

            public function permission(): ?string
            {
                return $this->permission;
            }

            public function build(DashboardScope $scope): array
            {
                return [];
            }
        };
    }

    /** A card of another class than the ones `card` makes. */
    private function anotherCard(string $key, DashboardAudience $audience): DashboardCardProvider
    {
        return new class($key, $audience) implements DashboardCardProvider
        {
            public function __construct(private string $key, private DashboardAudience $audience) {}

            public function key(): string
            {
                return $this->key;
            }

            public function audiences(): array
            {
                return [$this->audience];
            }

            public function permission(): ?string
            {
                return null;
            }

            public function build(DashboardScope $scope): array
            {
                return [];
            }
        };
    }

    public function test_the_cards_of_a_dashboard_come_back_in_the_order_of_their_keys(): void
    {
        $cards = new DashboardCards;
        $cards->add($this->card('jobs', [DashboardAudience::Admin, DashboardAudience::Company]), $this->card('applications', [DashboardAudience::Admin]));

        $this->assertSame(['applications', 'jobs'], array_map(fn (DashboardCardProvider $card): string => $card->key(), $cards->for(DashboardAudience::Admin)));
        $this->assertSame(['jobs'], array_map(fn (DashboardCardProvider $card): string => $card->key(), $cards->for(DashboardAudience::Company)));
        $this->assertSame([], $cards->for(DashboardAudience::JobSeeker));
    }

    public function test_the_permissions_the_cards_ask_for_are_listed_once_and_sorted(): void
    {
        $cards = new DashboardCards;
        $cards->add(
            $this->card('b', [DashboardAudience::Admin], 'jobs.manage'),
            $this->card('a', [DashboardAudience::Admin], 'companies.manage'),
            $this->card('c', [DashboardAudience::Admin], 'jobs.manage'),
            $this->card('d', [DashboardAudience::Admin]),
        );

        $this->assertSame(['companies.manage', 'jobs.manage'], $cards->permissionsFor(DashboardAudience::Admin));
    }

    public function test_two_different_cards_cannot_share_a_key_on_one_dashboard(): void
    {
        $cards = new DashboardCards;
        $cards->add($this->card('jobs', [DashboardAudience::Admin]));

        $this->expectException(InvalidArgumentException::class);

        $cards->add($this->anotherCard('jobs', DashboardAudience::Admin));
    }
}
