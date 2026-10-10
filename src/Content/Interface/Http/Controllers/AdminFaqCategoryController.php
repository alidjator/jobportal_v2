<?php

namespace Src\Content\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Content\Application\Faqs\CreateFaqCategory;
use Src\Content\Application\Faqs\DeleteFaqCategory;
use Src\Content\Application\Faqs\ListFaqCategories;
use Src\Content\Application\Faqs\UpdateFaqCategory;
use Src\Content\Interface\Http\Requests\FaqCategoryRequest;
use Src\Content\Interface\Http\Resources\AdminFaqCategoryResource;
use Src\Shared\Interface\Http\ApiController;

/** FAQ category management for admins holding `faqs.manage`. */
final class AdminFaqCategoryController extends ApiController
{
    public function index(ListFaqCategories $list): AnonymousResourceCollection
    {
        return AdminFaqCategoryResource::collection($list->execute());
    }

    public function store(FaqCategoryRequest $request, CreateFaqCategory $create): JsonResponse
    {
        $category = $create->execute($request->name(), $request->sortOrder(), $request->isActive());

        return (new AdminFaqCategoryResource($category))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(int $id, FaqCategoryRequest $request, UpdateFaqCategory $update): AdminFaqCategoryResource
    {
        return new AdminFaqCategoryResource($update->execute($id, $request->name(), $request->sortOrder(), $request->isActive()));
    }

    public function destroy(int $id, DeleteFaqCategory $delete): Response
    {
        $delete->execute($id);

        return response()->noContent();
    }
}
