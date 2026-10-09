<?php

namespace Src\JobSeeker\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\JobSeeker\Application\Cv\GenerateMyCv;
use Src\JobSeeker\Application\Cv\ListCvTemplates;
use Src\JobSeeker\Interface\Http\Requests\GenerateCvRequest;
use Src\JobSeeker\Interface\Http\Resources\CvTemplateResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** The CV generator of the job seeker: the templates to choose from, and the PDF made from the latest profile. */
final class CvController extends ApiController
{
    public function templates(Request $request, ActorResolver $actors, ListCvTemplates $list): AnonymousResourceCollection
    {
        return CvTemplateResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function generate(GenerateCvRequest $request, ActorResolver $actors, GenerateMyCv $generate): Response
    {
        $cv = $generate->execute($actors->fromRequest($request), $request->spec());
        $fallback = str_replace(['%', '/', '\\', '"'], '_', (string) preg_replace('/[^\x20-\x7E]/', '_', $cv->name));

        return new Response($cv->contents, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $cv->name, $fallback),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
