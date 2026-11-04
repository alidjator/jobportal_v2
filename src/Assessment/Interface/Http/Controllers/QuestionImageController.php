<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Assessment\Application\Authoring\GetQuestionImage;
use Src\Assessment\Application\Authoring\UploadQuestionImage;
use Src\Assessment\Interface\Http\QuestionImageResponse;
use Src\Assessment\Interface\Http\Requests\UploadQuestionImageRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The pictures of a test of the author: upload one (it is attached when a question names its id) and read one to preview it. */
final class QuestionImageController extends ApiController
{
    /** The answer is the picture to name in a question: its `id`, the size and the text for screen readers. */
    public function store(UploadQuestionImageRequest $request, int $id, ActorResolver $actors, UploadQuestionImage $upload): JsonResponse
    {
        $image = $upload->execute($actors->fromRequest($request), $id, $request->fileContents(), $request->alt());

        return response()->json(['data' => ['id' => $image->id, 'alt' => $image->alt, 'width' => $image->width, 'height' => $image->height, 'size_bytes' => $image->sizeBytes]], Response::HTTP_CREATED);
    }

    public function show(Request $request, int $id, int $imageId, ActorResolver $actors, GetQuestionImage $get): Response
    {
        return QuestionImageResponse::of($get->execute($actors->fromRequest($request), $id, $imageId));
    }
}
