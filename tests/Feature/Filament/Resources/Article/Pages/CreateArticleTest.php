<?php

declare(strict_types=1);

use Capell\Admin\Contracts\Extenders\PageSchemaExtender;
use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Enums\ConfiguratorTypeEnum;
use Capell\Admin\Enums\PageTranslationSchemaHookEnum;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Admin\Support\Schemas\AbstractPageSchemaExtender;
use Capell\Blog\Enums\BlogLayoutEnum;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Filament\Configurators\Articles\ArticlePageConfigurator;
use Capell\Blog\Filament\Resources\Articles\ArticleResource;
use Capell\Blog\Filament\Resources\Articles\Pages\CreateArticle;
use Capell\Blog\Filament\Resources\Articles\Pages\EditArticle;
use Capell\Blog\Filament\Resources\Articles\Pages\ListArticles;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)
    ->group('widget');

beforeEach(function (): void {
    test()->actingAsAdmin();
    Layout::query()->create(['key' => BlogLayoutEnum::Article->value, 'name' => 'Article Layout']);
});

describe('from edit article', function (): void {
    test('can create new article', function (): void {
        $article = Article::factory()->create();
        $newData = Article::factory()->recycle($article->site)->make();

        $slug = str($newData->name)->slug()->toString();

        $uuid = (string) Str::uuid();

        livewire(EditArticle::class, ['record' => $article->getRouteKey()])
            ->assertSuccessful()
            ->mountAction('create')
            ->fillForm([
                'name' => $newData->name,
                'blueprint_id' => $newData->blueprint_id,
                'site_id' => $newData->site_id,
            ])
            ->set('mountedActions.0.data.translations', [
                $uuid => [
                    'title' => $newData->name,
                    'language_id' => $article->site->language_id,
                    'meta' => ['slug' => $slug],
                ],
            ])
            ->set('mountedActions.0.data.translations.' . $uuid . '.meta.slug', $slug)
            ->callMountedAction()
            ->assertHasNoFormErrors();

        assertDatabaseHas(Article::class, [
            'name' => $newData->name,
            'blueprint_id' => $newData->blueprint_id,
            'layout_id' => $article->layout_id,
        ]);

        assertDatabaseHas(Translation::class, [
            'title' => $newData->name,
            'meta->slug' => $slug,
            'language_id' => $article->site->language_id,
        ]);

        assertDatabaseHas(PageUrl::class, [
            'url' => '/' . $slug,
        ]);
    });

    test('required fields are required', function (): void {
        $article = Article::factory()->create();

        livewire(EditArticle::class, ['record' => $article->getRouteKey()])
            ->assertSuccessful()
            ->callAction('create', [
                'translations' => [
                    'abc' => [
                        'language_id' => $article->site->language_id,
                        'title' => '',
                        'meta' => [
                            'slug' => '',
                        ],
                    ],
                ],
            ])
            ->assertHasFormErrors([
                'translations.abc.title' => 'required',
                'translations.abc.meta.slug' => 'required',
            ]);
    });
});

describe('from list article', function (): void {
    test('can create new article', function (): void {
        $blogCreator = resolve(BlogCreator::class);
        $type = $blogCreator->createArticlePageType();

        $language = Language::factory()->create();

        $site = Site::factory()
            ->recycle($language)
            ->hasSiteDomains()
            ->create();

        $newData = Article::factory()->recycle($site)->type($type)->make();

        $blogCreator->createArticleLayout(createWidgets: false);

        livewire(CreateArticle::class, ['type' => $type->key])
            ->assertSuccessful()
            ->set('data.translations', [])
            ->fillForm([
                'site_id' => $site->id,
                'name' => $newData->name,
            ])
            ->set(
                'data.translations',
                $site->languages->mapWithKeys(fn (Language $language): array => [
                    (string) Str::uuid() => [
                        'language_id' => $language->getKey(),
                        'title' => $newData->name,
                        'meta' => ['slug' => str($newData->name)->slug()->toString()],
                    ],
                ])
                    ->toArray(),
            )
            ->assertSchemaStateSet([
                'name' => $newData->name,
                'blueprint_id' => $type->id,
                'site_id' => $site->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        assertDatabaseHas(Article::class, [
            'name' => $newData->name,
        ]);
    });

    test('can create new article from list page', function (): void {
        $blogCreator = resolve(BlogCreator::class);

        $type = $blogCreator->createArticlePageType();
        $layout = $blogCreator->createArticleLayout(createWidgets: false);

        $language = Language::factory()->create();
        $site = Site::factory()->recycle($language)->hasSiteDomains()->create();

        $newData = Article::factory()->make();

        livewire(CreateArticle::class, ['type' => $type->key])
            ->assertSuccessful()
            ->assertSchemaComponentExists('blueprint_id', checkComponentUsing: fn (Component $component): bool => $component instanceof Hidden)
            ->set('data.translations', [])
            ->fillForm([
                'name' => $newData->name,
            ])
            ->set(
                'data.translations',
                $site->languages->mapWithKeys(fn (Language $language): array => [
                    (string) Str::uuid() => [
                        'language_id' => $language->getKey(),
                        'title' => $newData->name,
                        'meta' => ['slug' => str($newData->name)->slug()->toString()],
                    ],
                ])
                    ->toArray(),
            )
            ->assertSchemaStateSet([
                'name' => $newData->name,
                'layout_id' => $layout->id,
                'site_id' => $site->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        assertDatabaseHas(Article::class, [
            'name' => $newData->name,
            'site_id' => $site->id,
            'layout_id' => $layout->id,
        ]);

        $article = Article::query()
            ->where('name', $newData->name)
            ->first();
        $article = blogTestArticle($article);

        expect($article->blueprint)
            ->key->toBe(BlogPageTypeEnum::Article->value)
            ->group->toBe('article');
    });

    test('required fields are required', function (): void {
        $language = Language::factory()->create();
        Site::factory()->recycle($language)->hasSiteDomains()->create();
        $type = resolve(BlogCreator::class)->createArticlePageType();

        livewire(CreateArticle::class, ['type' => $type->key])
            ->assertSuccessful()
            ->call('create')
            ->assertHasErrors();
    });
});

test('the chosen article blueprint survives the complete create journey', function (): void {
    $default = resolve(BlogCreator::class)->createArticlePageType();
    $alternative = $default->replicate();
    $alternative->forceFill(['key' => 'article-interview', 'name' => 'Interview', 'default' => false])->save();
    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->hasSiteDomains()->create();

    livewire(ListArticles::class)
        ->callAction('create', ['blueprint_id' => $alternative->id])
        ->assertRedirect(ArticleResource::getUrl('create', ['type' => $alternative->key]));

    livewire(CreateArticle::class, ['type' => $alternative->key])
        ->set('data.translations', [])
        ->fillForm([
            'site_id' => $site->id,
            'name' => 'An interview',
        ])
        ->set('data.translations', [(string) Str::uuid() => [
            'language_id' => $language->id,
            'title' => 'An interview',
            'meta' => ['slug' => 'an-interview'],
        ]])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Article::class, ['name' => 'An interview', 'blueprint_id' => $alternative->id, 'site_id' => $site->id]);
    assertDatabaseHas(Translation::class, ['title' => 'An interview', 'language_id' => $language->id, 'meta->slug' => 'an-interview']);
    assertDatabaseHas(PageUrl::class, ['url' => '/an-interview']);
})->group('blog-editorial-create');

test('article persistence rejects disabled and non article blueprint selections', function (string $invalid): void {
    $type = resolve(BlogCreator::class)->createArticlePageType();
    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->hasSiteDomains()->create();
    if ($invalid === 'disabled') {
        $type->update(['status' => false]);
    } else {
        $type->update(['group' => 'page']);
    }

    $data = ['name' => 'Must not save', 'site_id' => $site->id, 'blueprint_id' => $type->id];

    expect(fn () => ArticleResource::mutateFormDataBeforeCreate($data))
        ->toThrow(ValidationException::class, __('capell-blog::generic.article_blueprint_unavailable'));
    expect(Article::query()->where('name', 'Must not save')->exists())->toBeFalse();
})->with(['disabled', 'non article'])->group('blog-editorial-create');

test('selected configurators and translation schema extenders survive article creation', function (): void {
    $type = resolve(BlogCreator::class)->createArticlePageType();
    $custom = new class extends ArticlePageConfigurator
    {
        public static function getKey(): string
        {
            return 'editorial-interview';
        }

        protected function getCreateExtraFor(Schema $schema): array
        {
            return [...parent::getCreateExtraFor($schema), TextInput::make('interview_note')->required()->dehydrated(false)];
        }
    };
    CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::configurator(
        class: $custom::class,
        group: ConfiguratorTypeEnum::Page->value,
        name: $custom::getKey(),
    ));
    $type->update(['admin' => [...($type->admin ?? []), 'configurator' => $custom::getKey()]]);
    $extender = new class extends AbstractPageSchemaExtender
    {
        public function extendTranslationComponentsForHook(Schema $schema, PageTranslationSchemaHookEnum $hook): array
        {
            return $hook === PageTranslationSchemaHookEnum::AfterTitle
                ? [TextInput::make('editorial_credit')->label('Editorial credit')->dehydrated(false)]
                : [];
        }
    };
    app()->instance($extender::class, $extender);
    app()->tag([$extender::class], PageSchemaExtender::TAG);

    $language = Language::factory()->create();
    Site::factory()->recycle($language)->hasSiteDomains()->create();

    livewire(CreateArticle::class, ['type' => $type->key])
        ->assertSchemaComponentExists('interview_note')
        ->assertSee('Editorial credit')
        ->call('create')
        ->assertHasFormErrors(['interview_note' => 'required']);
})->group('blog-editorial-extensions');

test('disabling a blueprint after opening the form prevents article persistence', function (): void {
    $type = resolve(BlogCreator::class)->createArticlePageType();
    $language = Language::factory()->create();
    Site::factory()->recycle($language)->hasSiteDomains()->create();
    $component = livewire(CreateArticle::class, ['type' => $type->key])
        ->set('data.translations', [(string) Str::uuid() => [
            'language_id' => $language->id,
            'title' => 'Do not publish',
            'meta' => ['slug' => 'do-not-publish'],
        ]]);
    $type->update(['status' => false]);

    $component->call('create')->assertHasErrors(['blueprint_id'])
        ->assertSee(__('capell-blog::generic.article_blueprint_unavailable'));

    expect(Article::query()->count())->toBe(0);
    expect($type->refresh()->status)->toBeFalse();
})->group('blog-editorial-stale');

test('a changed hidden blueprint cannot bypass the selected article schema', function (): void {
    $type = resolve(BlogCreator::class)->createArticlePageType();
    $other = $type->replicate();
    $other->forceFill(['key' => 'other-article', 'default' => false])->save();
    $language = Language::factory()->create();
    Site::factory()->recycle($language)->hasSiteDomains()->create();

    livewire(CreateArticle::class, ['type' => $type->key])
        ->set('data.blueprint_id', $other->id)
        ->call('create')
        ->assertHasFormErrors(['blueprint_id' => 'in']);

    expect(Article::query()->count())->toBe(0);
})->group('blog-editorial-extensions');
