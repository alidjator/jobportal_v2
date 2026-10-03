<?php

namespace Src\CandidateProfile\Application\Commands;

use Src\CandidateProfile\Domain\CandidateSettingsRepository;

/** POST /candidate/settings type=social. */
class UpdateSocialSettings
{
    public function __construct(private CandidateSettingsRepository $settings) {}

    /**
     * @param  array<int|string,string>  $socialMedia
     * @param  array<int|string,string>  $urls
     * @return array<string,mixed>
     */
    public function __invoke(int $userId, array $socialMedia, array $urls): array
    {
        if ($socialMedia !== [] && $urls !== []) {
            $links = [];

            foreach ($socialMedia as $key => $media) {
                $url = $urls[$key] ?? null;

                if ($media && $url) {
                    if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                        $url = 'https://'.$url;
                    }

                    $links[] = ['social_media' => $media, 'url' => $url];
                }
            }

            $this->settings->replaceSocial($userId, $links);
        }

        return [
            'message' => 'Social Link Updated Successful!',
            'social_media' => $this->settings->socialLinks($userId),
        ];
    }
}
