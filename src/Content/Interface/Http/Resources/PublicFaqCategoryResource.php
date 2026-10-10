<?php

namespace Src\Content\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Content\Application\Faqs\FaqCategoryWithFaqs;
use Src\Content\Domain\Faq;
use Src\Shared\Interface\Http\RequestLanguage;

/** A visible FAQ category with its visible questions, in the request language (Markdown answers). @property FaqCategoryWithFaqs $resource */
final class PublicFaqCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $language = RequestLanguage::of($request);

        return [
            'id' => $this->resource->category->id,
            'name' => $this->resource->category->name()->resolve($language),
            /** @var list<array{id: int, question: string, answer: string}> */
            'faqs' => array_map(fn (Faq $faq): array => [
                'id' => $faq->id,
                'question' => $faq->question()->resolve($language),
                'answer' => $faq->answer()->resolve($language),
            ], $this->resource->faqs),
        ];
    }
}
