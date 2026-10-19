<?php

namespace Tests\Unit\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Coordinates;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final class CoordinatesTest extends TestCase
{
    public function test_a_point_inside_the_box_of_indonesia_is_kept(): void
    {
        $point = Coordinates::of(-6.9, 107.6);

        $this->assertSame([-6.9, 107.6], [$point->latitude, $point->longitude]);
    }

    public function test_the_edges_of_the_box_are_inside_and_just_beyond_them_is_outside(): void
    {
        foreach ([[-11.0, 95.0], [6.0, 141.0], [-11.0, 141.0], [6.0, 95.0]] as [$latitude, $longitude]) {
            $this->assertTrue(Coordinates::fits($latitude, $longitude));
        }

        foreach ([[-11.01, 100.0], [6.01, 100.0], [0.0, 94.99], [0.0, 141.01]] as [$latitude, $longitude]) {
            $this->assertFalse(Coordinates::fits($latitude, $longitude));
        }
    }

    public function test_a_point_outside_is_refused_with_the_error_code(): void
    {
        try {
            Coordinates::of(51.5, -0.12);
            $this->fail('Expected a business rule violation.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('invalid-coordinates', $violation->errorCode());
        }
    }
}
