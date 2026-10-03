<?php

namespace Src\ReferenceData\Domain;

/** Data master sederhana: id + nama terjemahan. */
enum LookupType: string
{
    case JobRole = 'job_role';
    case Experience = 'experience';
    case Education = 'education';
    case JobType = 'job_type';
    case Profession = 'profession';

    public function table(): string
    {
        return match ($this) {
            self::JobRole => 'job_roles',
            self::Experience => 'experiences',
            self::Education => 'educations',
            self::JobType => 'job_types',
            self::Profession => 'professions',
        };
    }

    public function translationTable(): string
    {
        return $this->singular().'_translations';
    }

    public function foreignKey(): string
    {
        return $this->singular().'_id';
    }

    private function singular(): string
    {
        return match ($this) {
            self::JobRole => 'job_role',
            self::Experience => 'experience',
            self::Education => 'education',
            self::JobType => 'job_type',
            self::Profession => 'profession',
        };
    }
}
