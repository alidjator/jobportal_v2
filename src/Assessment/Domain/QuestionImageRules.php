<?php

namespace Src\Assessment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\ErrorMessage;

/**
 * Which questions may show pictures (`docs/design/phase-8-question-images.md` D2, D9): the choice, number and text questions show pictures above the text, a single or multiple choice question also has one picture per
 * option, a drawing question has one picture as its sheet. A scored statement (Likert, forced choice, most or least) and a video question show none: for a statement the wording is the item that was validated.
 */
final class QuestionImageRules
{
    public const int ALT_MAX = 300;

    /**
     * @param  int  $stem  how many pictures the question names
     * @param  list<bool>  $optionHasImage  one entry per option, true when the option names a picture
     *
     * @throws BusinessRuleViolation `invalid-question` (a type that takes no picture, a drawing with more than one) or `too-many-images`
     */
    public static function check(QuestionType $type, int $stem, array $optionHasImage, int $maxStem): void
    {
        $any = $stem > 0 || in_array(true, $optionHasImage, true);

        if (! $any) {
            return;
        }

        if (in_array($type, [QuestionType::Likert, QuestionType::ForcedChoice, QuestionType::MostLeast, QuestionType::VideoAnswer], true)) {
            throw self::invalid('image_ids', 'This question type takes no picture.');
        }

        if (in_array(true, $optionHasImage, true) && ! in_array($type, [QuestionType::SingleChoice, QuestionType::MultipleResponse], true)) {
            throw self::invalid('options', 'Only a single or multiple choice question has a picture on its options.');
        }

        if ($type === QuestionType::Drawing && $stem > 1) {
            throw self::invalid('image_ids', 'A drawing question has one picture, its sheet.');
        }

        if ($stem > $maxStem) {
            throw new BusinessRuleViolation('A question shows at most :max pictures.', 'too-many-images', extensions: ['errors' => ['image_ids' => [new ErrorMessage('A question shows at most :max pictures.', ['max' => $maxStem])]]], parameters: ['max' => $maxStem]);
        }
    }

    private static function invalid(string $field, string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-question', extensions: ['errors' => [$field => [$message]]]);
    }
}
