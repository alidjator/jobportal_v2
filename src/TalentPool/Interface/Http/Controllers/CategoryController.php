<?php

namespace Src\TalentPool\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\TalentPool\Application\CreateCategory;
use Src\TalentPool\Application\DeleteCategory;
use Src\TalentPool\Application\ListCategories;
use Src\TalentPool\Application\RenameCategory;
use Src\TalentPool\Interface\Http\Requests\CategoryRequest;
use Src\TalentPool\Interface\Http\Resources\CategoryResource;

/** The categories a company sorts its saved candidates into. */
final class CategoryController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListCategories $list): AnonymousResourceCollection
    {
        return CategoryResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function store(CategoryRequest $request, ActorResolver $actors, CreateCategory $create): JsonResponse
    {
        return (new CategoryResource($create->execute($actors->fromRequest($request), $request->categoryName())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(CategoryRequest $request, int $id, ActorResolver $actors, RenameCategory $rename): CategoryResource
    {
        return new CategoryResource($rename->execute($actors->fromRequest($request), $id, $request->categoryName()));
    }

    /** Deletes the category; its candidates stay in the pool, uncategorised. */
    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteCategory $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }
}
