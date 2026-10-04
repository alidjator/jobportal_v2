<?php

namespace Src\Content\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Content\Application\Faqs\ListPublicFaqs;
use Src\Content\Interface\Http\Resources\PublicFaqCategoryResource;
use Src\Shared\Interface\Http\ApiController;

/** The whole FAQ in one response (a bounded list, so no pagination). */
final class PublicFaqController extends ApiController
{
    public function index(ListPublicFaqs $listFaqs): AnonymousResourceCollection
    {
        return PublicFaqCategoryResource::collection($listFaqs->execute());
    }
}
