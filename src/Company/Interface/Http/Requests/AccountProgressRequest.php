<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Company\Domain\CompanyRepository;

/**
 * Satu langkah pengisian profil perusahaan. `step`: 1|company, 2|founding, 3|social, 4|contact
 * (aplikasi lama memakai nama; dokumentasi API memakai angka, keduanya diterima).
 */
class AccountProgressRequest extends FormRequest
{
    private const STEPS = ['1' => 'company', '2' => 'founding', '3' => 'social', '4' => 'contact'];

    private const SOCIAL_MEDIA = ['facebook', 'twitter', 'instagram', 'youtube', 'linkedin', 'pinterest', 'reddit', 'github', 'other'];

    public function step(): string
    {
        $step = (string) $this->input('step');

        return self::STEPS[$step] ?? $step;
    }

    public function rules(CompanyRepository $companies): array
    {
        $rules = ['step' => ['required', Rule::in([...array_keys(self::STEPS), ...array_values(self::STEPS)])]];

        return $rules + match ($this->step()) {
            'company' => $this->companyRules($companies),
            'founding' => [
                'organization_type_id' => ['required', 'max:255'],
                'industry_type_id' => ['required', 'max:255'],
                'establishment_date' => ['nullable', 'date'],
                'team_size_id' => ['nullable', 'integer'],
                'website' => ['nullable', 'url', 'max:255'],
                'vision' => ['required', 'string'],
            ],
            'social' => [
                'social_media' => ['nullable', 'array'],
                'social_media.*' => [function (string $attribute, mixed $value, \Closure $fail) {
                    $media = is_array($value) ? ($value['social_media'] ?? null) : $value;
                    if ($media !== null && $media !== '' && ! in_array($media, self::SOCIAL_MEDIA, true)) {
                        $fail('The selected social media is invalid.');
                    }
                }],
                'url' => ['nullable', 'array'],
                'url.*' => ['nullable', 'string', 'max:255'],
                'social_media.*.url' => ['nullable', 'string', 'max:255'],
            ],
            'contact' => [
                'phone' => ['required', 'min:4', 'max:16'],
                'email' => ['required', 'email', 'max:255'],
                'secondary_phone' => ['nullable', 'string', 'max:16'],
                'secondary_email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
                'neighborhood' => ['nullable', 'string', 'max:255'],
                'locality' => ['nullable', 'string', 'max:255'],
                'place' => ['nullable', 'string', 'max:255'],
                'district' => ['nullable', 'string', 'max:255'],
                'subdistrict' => ['nullable', 'integer'],
                'kelurahan' => ['nullable', 'integer'],
                'postcode' => ['nullable', 'string', 'max:255'],
                'region' => ['nullable', 'string', 'max:255'],
                'country' => ['nullable', 'string', 'max:255'],
                'long' => ['nullable', 'numeric'],
                'lat' => ['nullable', 'numeric'],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return ['image.required' => 'The logo field is required.'];
    }

    /** @return array<string,mixed> */
    private function companyRules(CompanyRepository $companies): array
    {
        $user = $this->user();
        $images = $user ? $companies->images((int) $user->getAuthIdentifier()) : ['logo' => null, 'banner' => null];

        return [
            'image' => [$images['logo'] ? 'sometimes' : 'required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'banner' => [$images['banner'] ? 'sometimes' : 'required', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'name' => ['nullable', 'max:255'],
            'bio' => ['required', 'string'],
        ];
    }

    /**
     * Gabungkan `social_media` format objek (`[{social_media,url}]`) dan format dua larik paralel.
     *
     * @return list<array{social_media:string,url:string}>
     */
    public function socialLinks(): array
    {
        $media = (array) $this->input('social_media', []);
        $urls = (array) $this->input('url', []);
        $links = [];

        foreach ($media as $key => $value) {
            [$name, $url] = is_array($value)
                ? [$value['social_media'] ?? null, $value['url'] ?? null]
                : [$value, $urls[$key] ?? null];

            if (filled($name) && filled($url)) {
                $links[] = ['social_media' => (string) $name, 'url' => (string) $url];
            }
        }

        return $links;
    }
}
