<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Assessment\Application\Provider\ChangeMySignature;
use Src\Assessment\Application\Provider\GetMySignature;
use Src\Assessment\Interface\Http\AttachmentResponse;
use Src\Assessment\Interface\Http\Requests\UploadImageRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The psychologist's own signature image, printed on the reports they sign. */
final class MySignatureController extends ApiController
{
    public function show(Request $request, ActorResolver $actors, GetMySignature $get): Response
    {
        return AttachmentResponse::of($get->execute($actors->fromRequest($request)));
    }

    public function store(UploadImageRequest $request, ActorResolver $actors, ChangeMySignature $change): Response
    {
        $change->put($actors->fromRequest($request), $request->contents());

        return response()->noContent();
    }

    public function destroy(Request $request, ActorResolver $actors, ChangeMySignature $change): Response
    {
        $change->remove($actors->fromRequest($request));

        return response()->noContent();
    }
}
