<?php

namespace Database\Seeders\Reference;

/**
 * Skill of a job or candidate. The 29 values and their order come from the legacy `skills` table,
 * which stores no slugs and no kind. The English names were tidied for this project (capitalisation,
 * product spelling, wording such as "Problem Solving"), and the kind was assigned here: the four
 * personal skills are soft, the rest are hard. Technical terms stay in English in the Indonesian
 * names; the others were translated for this project.
 */
class SkillSeeder extends MasterSeeder
{
    protected function table(): string
    {
        return 'reference_skills';
    }

    protected function rows(): array
    {
        return [
            $this->hard('Python', 'Python'),
            $this->hard('IT Support', 'IT Support'),
            $this->hard('Hardware & Software Troubleshooting', 'Pemecahan Masalah Perangkat Keras & Lunak'),
            $this->hard('Networking', 'Jaringan'),
            $this->hard('MS Project', 'MS Project'),
            $this->hard('Access Control', 'Kontrol Akses'),
            $this->hard('Audio Visual', 'Audio Visual'),
            $this->hard('AutoCAD', 'AutoCAD'),
            $this->hard('Data Center', 'Pusat Data'),
            $this->hard('Interior Design', 'Desain Interior'),
            $this->hard('Fire Alarm', 'Alarm Kebakaran'),
            $this->hard('Mechanical & Electrical', 'Mekanikal & Elektrikal'),
            $this->hard('SketchUp', 'SketchUp'),
            $this->hard('Smart Building', 'Gedung Pintar'),
            $this->soft('Problem Solving', 'Pemecahan Masalah'),
            $this->soft('Communication', 'Komunikasi'),
            $this->soft('Teamwork', 'Kerja Tim'),
            $this->soft('Analytical Thinking', 'Berpikir Analitis'),
            $this->hard('Figma', 'Figma'),
            $this->hard('Draw.io', 'Draw.io'),
            $this->hard('Microsoft SQL', 'Microsoft SQL'),
            $this->hard('SQL', 'SQL'),
            $this->hard('Excel', 'Excel'),
            $this->hard('Microsoft Office', 'Microsoft Office'),
            $this->hard('Data Visualization', 'Visualisasi Data'),
            $this->hard('Tableau', 'Tableau'),
            $this->hard('Power BI', 'Power BI'),
            $this->hard('R Programming', 'Pemrograman R'),
            $this->hard('Google Looker Studio', 'Google Looker Studio'),
        ];
    }

    /**
     * @return array{name: array{id: string, en: string}, extra: array{kind: string}}
     */
    private function hard(string $en, string $id): array
    {
        return ['name' => ['id' => $id, 'en' => $en], 'extra' => ['kind' => 'hard']];
    }

    /**
     * @return array{name: array{id: string, en: string}, extra: array{kind: string}}
     */
    private function soft(string $en, string $id): array
    {
        return ['name' => ['id' => $id, 'en' => $en], 'extra' => ['kind' => 'soft']];
    }
}
