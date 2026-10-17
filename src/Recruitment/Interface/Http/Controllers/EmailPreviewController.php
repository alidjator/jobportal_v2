<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Recruitment\Application\EmailPreview;
use Src\Recruitment\Application\PreviewApplicantEmail;
use Src\Recruitment\Interface\Http\Requests\EmailPreviewRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** Shows the email an interview, a test or an offer would send to the applicant, before the recruiter sends it. */
final class EmailPreviewController extends ApiController
{
    public function __invoke(EmailPreviewRequest $request, int $id, ActorResolver $actors, PreviewApplicantEmail $preview): JsonResponse
    {
        $actor = $actors->fromRequest($request);

        $result = match ($request->scenario()) {
            'assessment_invited' => $preview->assessment($actor, $id, $request->assessment()),
            'offer_sent' => $preview->offer($actor, $id, $request->offer()),
            'interview_updated' => $preview->interviewNote($actor, $id, $request->note()),
            default => $preview->interview($actor, $id, $request->interview()),
        };

        return response()->json(['data' => $this->data($result)]);
    }

    /** @return array<string, mixed> */
    private function data(EmailPreview $preview): array
    {
        return [
            'template' => $preview->template,
            'subject' => $preview->subject,
            'html' => $preview->html,
            'text' => $preview->text,
            'locale' => $preview->locale,
            'email_enabled' => $preview->emailEnabled,
            'calendar_attached' => $preview->calendarAttached,
        ];
    }
}
