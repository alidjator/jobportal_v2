<?php

namespace Src\Content\Infrastructure\Persistence\Eloquent;

use Src\Content\Domain\FaqCategory;
use Src\Content\Domain\FaqCategoryRepository;
use Src\Shared\Domain\TranslatedText;

final class EloquentFaqCategoryRepository implements FaqCategoryRepository
{
    public function findById(int $id): ?FaqCategory
    {
        $model = FaqCategoryModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function list(bool $onlyActive): array
    {
        return FaqCategoryModel::query()
            ->when($onlyActive, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (FaqCategoryModel $model): FaqCategory => $this->toDomain($model))
            ->values()
            ->all();
    }

    public function hasFaqs(int $id): bool
    {
        return FaqModel::query()->where('category_id', $id)->exists();
    }

    public function save(FaqCategory $category): FaqCategory
    {
        $model = $category->id === null ? new FaqCategoryModel : FaqCategoryModel::query()->findOrFail($category->id);

        $model->forceFill(['sort_order' => $category->sortOrder(), 'is_active' => $category->isActive()]);
        $model->setTranslations('name', $category->name()->toArray());
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        FaqCategoryModel::query()->whereKey($id)->delete();
    }

    private function toDomain(FaqCategoryModel $model): FaqCategory
    {
        return new FaqCategory($model->id, TranslatedText::fromArray($model->getTranslations('name')), $model->sort_order, $model->is_active);
    }
}
