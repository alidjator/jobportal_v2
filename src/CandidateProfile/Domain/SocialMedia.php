<?php

namespace Src\CandidateProfile\Domain;

/** Jenis tautan sosial (sama dengan `App\Enums\SocialMediaEnum` aplikasi lama). */
enum SocialMedia: string
{
    case Facebook = 'facebook';
    case Twitter = 'twitter';
    case Instagram = 'instagram';
    case Youtube = 'youtube';
    case Linkedin = 'linkedin';
    case Pinterest = 'pinterest';
    case Reddit = 'reddit';
    case Github = 'github';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return array<string,string> kunci => label (`facebook` => `Facebook`)
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
