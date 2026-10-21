<?php

namespace Tests\Unit\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ErrorMessage;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

final class MessageTemplateTest extends TestCase
{
    public function test_the_message_is_the_english_text_with_the_values_and_the_template_is_kept(): void
    {
        $error = new BusinessRuleViolation('The score must be a whole number from 0 to :points.', 'invalid-score', parameters: ['points' => 5]);

        $this->assertSame('The score must be a whole number from 0 to 5.', $error->getMessage());
        $this->assertSame('The score must be a whole number from 0 to :points.', $error->template());
        $this->assertSame(['points' => 5], $error->parameters());
    }

    public function test_longer_names_are_filled_first(): void
    {
        $this->assertSame('From 1 to 10 (limit 20).', DomainException::fill('From :min to :max (limit :max_total).', ['min' => 1, 'max' => 10, 'max_total' => 20]));
    }

    public function test_an_error_message_gives_its_template_and_values_to_the_error(): void
    {
        $error = new BusinessRuleViolation(new ErrorMessage('Choose at most :max assessors.', ['max' => 3]), 'invalid-assessor');

        $this->assertSame('Choose at most 3 assessors.', $error->getMessage());
        $this->assertSame('Choose at most :max assessors.', $error->template());
        $this->assertSame(['max' => 3], $error->parameters());
    }

    public function test_an_error_message_is_the_filled_english_text_as_a_string_and_in_json(): void
    {
        $message = new ErrorMessage('The section ":section" needs at least one question.', ['section' => 'Logic']);

        $this->assertSame('The section "Logic" needs at least one question.', (string) $message);
        $this->assertSame('["The section \"Logic\" needs at least one question."]', json_encode([$message]));
    }

    public function test_an_entity_not_found_keeps_the_entity_in_the_template_and_the_id_as_a_value(): void
    {
        $error = EntityNotFound::of('Job', 7);

        $this->assertSame('Job not found: 7', $error->getMessage());
        $this->assertSame('Job not found: :id', $error->template());
        $this->assertSame('Job not found', EntityNotFound::of('Job')->template());
    }

    public function test_a_throttle_error_takes_values_too(): void
    {
        $error = new TooManyAttempts('Wait :seconds seconds.', 30, 'probe', parameters: ['seconds' => 30]);

        $this->assertSame('Wait 30 seconds.', $error->getMessage());
        $this->assertSame(30, $error->retryAfterSeconds);
    }
}
