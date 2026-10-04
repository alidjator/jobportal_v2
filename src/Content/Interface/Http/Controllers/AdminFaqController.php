<?php

namespace Src\Content\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Content\Application\Faqs\CreateFaq;
use Src\Content\Application\Faqs\DeleteFaq;
use Src\Content\Application\Faqs\ListFaqs;
use Src\Content\Application\Faqs\UpdateFaq;
use Src\Content\Interface\Http\Requests\FaqRequest;
use Src\Content\Interface\Http\Requests\ListFaqsRequest;
use Src\Content\Interface\Http\Resources\AdminFaqResource;
use Src\Shared\Interface\Http\ApiController;

/** FAQ question management for admins holding `faqs.manage`. */
final class AdminFaqController extends ApiController
{
    public function index(ListFaqsRequest $request, ListFaqs $list): AnonymousResourceCollection
    {
        return AdminFaqResource::collection($list->execute($request->categoryId()));
    }

    public function store(FaqRequest $request, CreateFaq $create): JsonResponse
    {
        $faq = $create->execute($request->categoryId(), $request->question(), $request->answer(), $request->sortOrder(), $request->isActive());

        return (new AdminFaqResource($faq))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(int $id, FaqRequest $request, UpdateFaq $update): AdminFaqResource
    {
        return new AdminFaqResource($update->execute($id, $request->categoryId(), $request->question(), $request->answer(), $request->sortOrder(), $request->isActive()));
    }

    public function destroy(int $id, DeleteFaq $delete): Response
    {
        $delete->execute($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
