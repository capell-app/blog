<?php

declare(strict_types=1);

namespace Capell\Blog\Database\Factories;

use Capell\Blog\Actions\EnsureArticlePublishingDefaultsAction;
use Capell\Blog\Enums\BlogLayoutEnum;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Models\Article;
use Capell\Core\Database\Factories\Concerns\HasAdmin;
use Capell\Core\Database\Factories\Concerns\HasFactoryPublishDates;
use Capell\Core\Database\Factories\Concerns\HasMeta;
use Capell\Core\Database\Factories\Concerns\HasTranslations;
use Capell\Core\Enums\TypeEnum;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Site;
use Capell\Core\Models\Type;
use Capell\Tags\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    use HasAdmin;
    use HasFactoryPublishDates;
    use HasMeta;
    use HasTranslations;

    protected $model = Article::class;

    public function definition(): array
    {
        return [
            'name' => fn () => $this->faker->realTextBetween(2, 60),
            'layout_id' => fn (): int => $this->resolveArticleLayout()->id,
            'blueprint_id' => fn (): int => $this->resolveArticlePageType()->id,
            'site_id' => Site::factory()->withTranslations(),
            'created_at' => fn () => $this->faker->dateTimeBetween('-1 year', '-6 month'),
            'updated_at' => fn (array $attributes) => $this->faker->dateTimeBetween($attributes['created_at']),
        ];
    }

    public function layout(Layout $layout): static
    {
        return $this->set('layout_id', $layout->id);
    }

    public function site(int|Site $site): static
    {
        return $this->set('site_id', $site instanceof Site ? $site->id : $site);
    }

    public function type(Type $type): static
    {
        return $this->set('blueprint_id', $type->id);
    }

    public function withTags(): self
    {
        return $this->afterCreating(function (Article $article): void {
            if (Tag::query()->count() < 10) {
                Tag::factory()->count(3)->create();
            }

            $tags = Tag::query()->inRandomOrder()->limit(fake()->numberBetween(1, 3))->get();

            $article->tags()->attach($tags);
        });
    }

    private function resolveArticleLayout(): Layout
    {
        EnsureArticlePublishingDefaultsAction::run();

        return Layout::query()
            ->where('key', BlogLayoutEnum::Article->value)
            ->firstOrFail();
    }

    private function resolveArticlePageType(): Type
    {
        EnsureArticlePublishingDefaultsAction::run();

        return Type::query()
            ->where('key', BlogPageTypeEnum::Article->value)
            ->where('type', TypeEnum::Page->value)
            ->firstOrFail();
    }
}
