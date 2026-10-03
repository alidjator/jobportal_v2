<?php

namespace Src\CandidateProfile\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\CandidateProfile\Domain\Availability;
use Src\CandidateProfile\Domain\SocialMedia;

/** Aturan validasi bergantung pada field `type` (sama dengan aplikasi lama, dengan pembatas tipe/enum tambahan). */
class UpdateCandidateSettingsRequest extends FormRequest
{
    public const TYPES = ['personal', 'profile', 'social', 'contact', 'password', 'account-delete'];

    private const URL_PATTERN = 'regex:(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})';

    public function authorize(): bool
    {
        return true;
    }

    public function type(): string
    {
        return (string) $this->input('type');
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['type' => ['required', Rule::in(self::TYPES)]] + match ($this->type()) {
            'personal' => [
                'image' => ['image', 'mimes:jpeg,png,jpg', 'max:2048'],
                'name' => 'required|max:100',
                'title' => 'required|max:100',
                'experience_id' => ['required', 'integer', 'exists:experiences,id'],
                'education_id' => ['required', 'integer', 'exists:education,id'],
                'date_of_birth' => 'required|date',
                'website' => 'nullable|string|max:255',
            ],
            'profile' => [
                'gender' => ['required', Rule::in(['male', 'female'])],
                'marital_status' => 'required|string|max:255',
                'profession' => 'required|string|max:255',
                'status' => ['required', Rule::enum(Availability::class)],
                'bio' => 'required|string',
                'available_in' => ['nullable', 'date', Rule::requiredIf($this->input('status') === Availability::AvailableIn->value)],
                'skills' => 'nullable|array',
                'skills.*' => 'string|max:255',
                'languages' => 'nullable|array',
                'languages.*' => ['integer', 'exists:candidate_languages,id'],
            ],
            'social' => [
                'social_media' => 'nullable|array',
                'social_media.*' => ['required', Rule::enum(SocialMedia::class)],
                'url' => 'nullable|array',
                'url.*' => ['required', 'max:255', self::URL_PATTERN],
            ],
            'contact' => [
                'phone' => 'required|string|max:255',
                'secondary_phone' => 'nullable|string|max:255',
                'email' => 'required|email|max:255',
                'secondary_email' => 'nullable|email|max:255',
                'whatsapp_number' => 'nullable|string|max:255',
                'country' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:255',
                'exact_location' => 'nullable|string|max:255',
                'lat' => 'nullable|numeric',
                'long' => 'nullable|numeric',
            ],
            'password' => [
                'current_password' => 'required',
                'password' => 'required|confirmed|min:8',
                'password_confirmation' => 'required|min:8|same:password',
            ],
            'account-delete' => [
                'current_password' => 'required',
            ],
            default => [],
        };
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'experience_id.required' => 'The experience field is required.',
            'education_id.required' => 'The education field is required.',
            'social_media.*.required' => 'The social media field is required.',
            'url.*.required' => 'The url field is required.',
            'url.*.regex' => 'The url field must be a valid URL with www or https.',
        ];
    }
}
