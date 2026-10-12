<?php

namespace Src\Assessment\Application\Video;

use Src\Assessment\Application\Attempts\AttemptContext;
use Src\Assessment\Application\Attempts\AttemptQuestions;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionType;
use Src\Assessment\Domain\VideoTake;
use Src\Assessment\Domain\VideoTakeRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Finds the video question and the take a candidate works on, and checks they belong to the open attempt. */
final readonly class VideoTakes
{
    /** Where the chunks of a take wait until it is complete, and where the joined recordings live. */
    public const string CHUNKS = 'assessment/video-chunks';

    public const string VIDEOS = 'assessment/videos';

    public function __construct(
        private AttemptQuestions $reach,
        private VideoTakeRepository $takes,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-answer` when the question is not a video question
     * @throws StateConflict `question-locked`
     */
    public function question(AttemptContext $context, int $questionId): Question
    {
        $question = $this->reach->reachable($context, $questionId);

        if ($question->type !== QuestionType::VideoAnswer || $question->answerSeconds === null || $question->maxRetakes === null) {
            throw new BusinessRuleViolation('This question is not answered with a video.', 'invalid-answer', extensions: ['errors' => ['question' => ['This question is not answered with a video.']]]);
        }

        return $question;
    }

    /** The take, locked for the write; another candidate's or another question's take is a 404. */
    public function take(AttemptContext $context, int $questionId, int $takeId): VideoTake
    {
        $take = $this->takes->find($takeId, forUpdate: true);

        if (! $take instanceof VideoTake || $take->attemptId !== (int) $context->attempt->id || $take->questionId !== $questionId) {
            throw EntityNotFound::of('Video take', $takeId);
        }

        return $take;
    }

    public static function chunkPath(int $takeId, int $index): string
    {
        return self::CHUNKS.'/'.$takeId.'/'.$index;
    }
}
