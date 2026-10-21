<?php

namespace Tests\Feature\Shared;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ErrorMessage;
use Src\Shared\Domain\Exceptions\TooManyAttempts;
use Src\Shared\Infrastructure\ContextServiceProvider;
use Tests\FeatureTestCase;

/** The problem texts in the request language (`lang/id.json`, `lang/id/validation.php`). The test client sends `Accept-Language: en` unless told otherwise. */
final class ProblemDetailsTranslationTest extends FeatureTestCase
{
    private const string PREFIX = ContextServiceProvider::API_PREFIX.'/probe-language';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'id');

        Route::middleware('api')->prefix(self::PREFIX)->group(function (): void {
            Route::get('not-found', fn () => throw EntityNotFound::of('Job', 7));
            Route::get('template', fn () => throw new BusinessRuleViolation(
                'The note can have at most :max characters.', 'invalid-note',
                extensions: ['errors' => [
                    'note' => ['The note can have at most :max characters.'],
                    'score' => [new ErrorMessage('The score must be between :min and :max.', ['min' => 1, 'max' => 5])],
                ]],
                parameters: ['max' => 200],
            ));
            Route::get('untranslated', fn () => throw new BusinessRuleViolation('Probe number :number has no translation.', 'probe', parameters: ['number' => 3]));
            Route::get('throttled', fn () => throw new TooManyAttempts('You can save at most :max jobs. Remove one first.', 30, 'probe-limit', parameters: ['max' => 4]));
            Route::get('validation', fn (Request $request) => $request->validate(['email' => ['required']]));
            Route::get('middleware', fn () => throw ValidationException::withMessages(['tenant_id' => ['The tenant_id query parameter is required: the tenant whose recruitment you read.']]));
        });
    }

    public function test_the_title_and_the_detail_are_indonesian(): void
    {
        $this->getJson(self::PREFIX.'/not-found')
            ->assertStatus(404)
            ->assertExactJson([
                'type' => 'urn:onesis:error:not-found',
                'title' => 'Tidak Ditemukan',
                'status' => 404,
                'detail' => 'Lowongan tidak ditemukan: 7',
            ]);
    }

    public function test_the_values_fill_the_translated_template_and_the_messages_of_errors(): void
    {
        $this->getJson(self::PREFIX.'/template')
            ->assertStatus(422)
            ->assertJsonPath('title', 'Konten Tidak Dapat Diproses')
            ->assertJsonPath('detail', 'Catatan dapat berisi paling banyak 200 karakter.')
            ->assertJsonPath('errors.note.0', 'Catatan dapat berisi paling banyak 200 karakter.')
            ->assertJsonPath('errors.score.0', 'Skor harus antara 1 dan 5.');
    }

    public function test_english_is_given_when_the_request_asks_for_it(): void
    {
        $this->getJson(self::PREFIX.'/template', ['Accept-Language' => 'en'])
            ->assertStatus(422)
            ->assertJsonPath('title', 'Unprocessable Content')
            ->assertJsonPath('detail', 'The note can have at most 200 characters.')
            ->assertJsonPath('errors.score.0', 'The score must be between 1 and 5.');
    }

    public function test_a_text_without_a_translation_stays_english_with_its_values(): void
    {
        $this->getJson(self::PREFIX.'/untranslated')->assertStatus(422)->assertJsonPath('detail', 'Probe number 3 has no translation.');
    }

    public function test_a_throttle_error_is_translated_too(): void
    {
        $this->getJson(self::PREFIX.'/throttled')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '30')
            ->assertJsonPath('detail', 'Anda dapat menyimpan paling banyak 4 lowongan. Hapus salah satunya terlebih dahulu.');
    }

    public function test_validation_messages_use_the_indonesian_lines_and_field_names(): void
    {
        $this->getJson(self::PREFIX.'/validation')
            ->assertStatus(422)
            ->assertJsonPath('detail', 'Data permintaan tidak valid.')
            ->assertJsonPath('errors.email.0', 'Email wajib diisi.');
    }

    public function test_a_validation_message_written_by_a_middleware_is_translated(): void
    {
        $this->getJson(self::PREFIX.'/middleware')
            ->assertStatus(422)
            ->assertJsonPath('errors.tenant_id.0', 'Parameter kueri tenant_id wajib diisi: tenant yang data rekrutmennya Anda lihat.');
    }
}
