<?php

namespace Tests\Unit\Shared\Application;

use PHPUnit\Framework\TestCase;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;

final class ActorTest extends TestCase
{
    public function test_account_actors_carry_their_kind_and_id(): void
    {
        $this->assertSame(ActorKind::JobSeeker, Actor::jobSeeker(1)->kind);
        $this->assertSame(ActorKind::CompanyMember, Actor::companyMember(2)->kind);
        $this->assertSame(3, Actor::admin(3)->id);
        $this->assertTrue(Actor::admin(3)->isAdmin());
        $this->assertFalse(Actor::jobSeeker(1)->isAdmin());
    }

    public function test_the_system_actor_has_no_id(): void
    {
        $system = Actor::system();

        $this->assertTrue($system->isSystem());
        $this->assertNull($system->id);
    }
}
