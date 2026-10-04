<?php

namespace Src\Content\Infrastructure\Persistence\Eloquent;

use Src\Content\Domain\Faq;
use Src\Content\Domain\FaqRepository;
use Src\Shared\Domain\TranslatedText;

final class EloquentFaqRepository implements FaqRepository
{
    public function findById(int $id): ?Faq
    {
        $model = FaqModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function list(?int $categoryId, bool $onlyActive): array
    {
        return FaqModel::query()
            ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId))
            ->when($onlyActive, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (FaqModel $model): Faq => $this->toDomain($model))
            ->values()
            ->all();
    }

    public function save(Faq $faq): Faq
    {
        $model = $faq->id === null ? new FaqModel : FaqModel::query()->findOrFail($faq->id);

        $model->forceFill(['category_id' => $faq->categoryId(), 'sort_order' => $faq->sortOrder(), 'is_active' => $faq->isActive()]);
        $model->setTranslations('question', $faq->question()->toArray());
        $model->setTranslations('answer', $faq->answer()->toArray());
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        FaqModel::query()->whereKey($id)->delete();
    }

    private function toDomain(FaqModel $model): Faq
    {
        return new Faq(
            $model->id,
            $model->category_id,
            TranslatedText::fromArray($model->getTranslations('question')),
            TranslatedText::fromArray($model->getTranslations('answer')),
            $model->sort_order,
            $model->is_active,
        );
    }
}
