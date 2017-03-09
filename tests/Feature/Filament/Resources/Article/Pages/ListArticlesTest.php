<?php

declare(strict_types=1);

use Capell\Admin\Contracts\Extenders\ResourceHeaderActionExtender;
use Capell\Admin\Contracts\Pages\PageTableStatusResolver;
use Capell\Admin\Data\ImportEntryData;
use Capell\Admin\Data\Pages\PageTableStatusData;
use Capell\Admin\Settings\AdminSettings;
use Capell\Admin\Support\ImportEntryRegistry;
use Capell\Admin\Support\Pages\DefaultPageTableStatusResolver;
use Capell\Blog\Data\ArticleTranslationCoverageData;
use Capell\Blog\Filament\Resources\Articles\ArticleResource;
use Capell\Blog\Filament\Resources\Articles\Pages\ListArticles;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Contracts\Publishable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)
    ->group('page', 'article');

beforeEach(function (): void {
    test()->actingAsAdmin();
});

test('can list articles', function (): void {
    Page::factory()->withTranslations()->count(5)->create();

    $pages = Article::factory()->withTranslations()->withTags()->count(5)->create();

    livewire(ListArticles::class)
        ->assertSuccessful()
        ->assertCountTableRecords(5)
        ->assertCanSeeTableRecords($pages);
});

test('new article goes straight to the only eligible blueprint and removes generic peers', function (): void {
    $type = resolve(BlogCreator::class)->createArticlePageType();

    $component = livewire(ListArticles::class)
        ->assertActionHasLabel('create', __('capell-blog::generic.new_article'))
        ->assertActionHasUrl('create', ArticleResource::getUrl('create', ['type' => $type->key]))
        ->assertSee(__('capell-blog::generic.empty_articles'));

    $instance = $component->instance();
    $this->assertInstanceOf(ListArticles::class, $instance);
    expect(collect($instance->getCachedHeaderActions())
        ->filter(fn (Action|ActionGroup $action): bool => $action instanceof Action)
        ->map->getName()->values()->all())->toBe(['create']);
})->group('blog-editorial-queue');

test('new article chooses an eligible blueprint within one journey', function (): void {
    $default = resolve(BlogCreator::class)->createArticlePageType();
    $alternative = $default->replicate();
    $alternative->forceFill(['key' => 'article-interview', 'name' => 'Interview', 'default' => false])->save();

    livewire(ListArticles::class)
        ->mountAction('create')
        ->fillForm(['blueprint_id' => $alternative->getKey()])
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertRedirect(ArticleResource::getUrl('create', ['type' => 'article-interview']));
})->group('blog-editorial-queue');

test('zero eligible blueprints disables creation without reviving disabled choices', function (): void {
    $type = resolve(BlogCreator::class)->createArticlePageType();
    $type->update(['status' => false]);

    livewire(ListArticles::class)->assertActionDisabled('create');

    expect($type->refresh()->status)->toBeFalse();
    expect(Blueprint::query()->pageType()->where('group', 'article')->enabled()->count())->toBe(0);
})->group('blog-editorial-queue');

test('the editorial queue leads with title state languages and last update', function (): void {
    $article = Article::factory()->withTranslations()->create();

    $component = livewire(ListArticles::class)
        ->assertTableColumnStateSet('name', [$article->translation->title], $article)
        ->assertTableColumnExists('publication_state')
        ->assertTableColumnExists('translation_coverage');

    $instance = $component->instance();
    $this->assertInstanceOf(ListArticles::class, $instance);
    $columns = $instance->getTable()->getColumns();
    expect(array_slice(array_keys($columns), 0, 5))->toBe(['name', 'publication_state', 'site.name', 'translation_coverage', 'updated_at']);
    foreach (['id', 'image', 'layout.name', 'type.name', 'creator.name', 'created_at'] as $name) {
        $component->assertTableColumnExists($name, fn (Column $column): bool => $column->isToggledHiddenByDefault());
    }
})->group('blog-editorial-queue');

test('the queue uses the publication contract for article states', function (string $state, string $from, ?string $until, string $label): void {
    $this->travelTo(new CarbonImmutable('2026-09-07 12:00:00'));
    $article = Article::factory()->withTranslations()->create(['visible_from' => $from, 'visible_until' => $until]);

    livewire(ListArticles::class)->assertTableColumnStateSet('publication_state', $label, $article);
    expect($article->publishVisibilityState()->value)->toBe($state);
})->with([
    ['draft', '2126-09-07 12:00:00', null, 'Draft'],
    ['scheduled', '2026-09-09 12:00:00', null, '2d'],
    ['published', '2026-09-06 12:00:00', null, 'Published'],
    ['expired', '2026-09-05 12:00:00', '2026-09-06 12:00:00', 'Expired'],
])->group('blog-editorial-states');

test('translation gaps are separate from publication state and scoped to site languages', function (): void {
    $english = Language::factory()->create(['name' => 'English']);
    $french = Language::factory()->create(['name' => 'French']);
    $site = Site::factory()->recycle($english)->create();
    $site->translations()->create(['language_id' => $french->id, 'title' => 'Site']);
    $article = Article::factory()->recycle($site)->create();
    $article->translations()->create(['language_id' => $english->id, 'title' => 'Article', 'meta' => ['slug' => 'article']]);
    $article->load(['site.languages', 'translations']);

    expect(ArticleTranslationCoverageData::fromArticle($article)->missingLanguages)->toBe(['French']);
    expect(ArticleTranslationCoverageData::fromArticle($article, $english->id)->missingLanguages)->toBe([]);
    livewire(ListArticles::class)->assertSee('Incomplete translation: French')
        ->filterTable('filter', ['language_id' => (string) $english->id])
        ->assertDontSee('Incomplete translation: French')
        ->assertCanSeeTableRecords([$article]);
})->group('blog-editorial-states');

test('editorial row relationships remain eager loaded as the queue grows', function (): void {
    $article = Article::factory()->withTranslations()->create();
    livewire(ListArticles::class);
    DB::enableQueryLog();
    DB::flushQueryLog();
    livewire(ListArticles::class);
    $small = count(DB::getQueryLog());
    Article::factory()->recycle($article->site)->withTranslations()->count(4)->create();
    DB::flushQueryLog();

    livewire(ListArticles::class)->assertCountTableRecords(5);
    $large = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($large)->toBeLessThanOrEqual($small + 2);
})->group('blog-editorial-query');

test('the queue honours publication resolver query contributions', function (): void {
    $article = Article::factory()->withTranslations()->create();
    app()->instance(PageTableStatusResolver::class, new class extends DefaultPageTableStatusResolver
    {
        public function modifyQuery(Builder $query): Builder
        {
            return $query->addSelect($query->getModel()->qualifyColumn('*'))->selectRaw('1 as editorial_status_ready');
        }

        public function resolve(Model&Pageable&Publishable $page): PageTableStatusData
        {
            return new PageTableStatusData(
                label: 'Editorial review',
                shortLabel: $page->getAttribute('editorial_status_ready') === 1 ? 'Editorial review' : 'Missing query contribution',
                tooltip: null,
                color: 'gray',
                icon: null,
            );
        }
    });

    livewire(ListArticles::class)->assertTableColumnStateSet('publication_state', 'Editorial review', $article);
})->group('blog-editorial-extensions');

test('import remains secondary and honours registry permissions alongside header extenders', function (): void {
    resolve(BlogCreator::class)->createArticlePageType();
    resolve(AdminSettings::class)->enable_import_export = true;
    foreach (['allowed' => true, 'denied' => false] as $key => $allowed) {
        resolve(ImportEntryRegistry::class)->register(new ImportEntryData(
            key: $key,
            labelKey: 'capell-admin::exchanger.import.action_label',
            descriptionKey: null,
            icon: 'heroicon-o-arrow-up-tray',
            sort: 1,
            pageClasses: [ListArticles::class],
            actionFactory: fn (): Action => Action::make($key)->url('/import-' . $key),
            authorize: fn (): bool => $allowed,
        ));
    }

    $extender = new class implements ResourceHeaderActionExtender
    {
        public function supports(string $pageClass): bool
        {
            return $pageClass === ListArticles::class;
        }

        public function actions(): array
        {
            return [Action::make('editorial-extension')->url('/editorial-extension')];
        }
    };
    app()->instance($extender::class, $extender);
    app()->tag([$extender::class], ResourceHeaderActionExtender::TAG);

    $component = livewire(ListArticles::class)->assertActionHasUrl('editorial-extension', '/editorial-extension');
    $instance = $component->instance();
    $this->assertInstanceOf(ListArticles::class, $instance);
    $header = $instance->getCachedHeaderActions();
    $this->assertInstanceOf(Action::class, $header[0]);
    $this->assertInstanceOf(ActionGroup::class, $header[1]);
    expect($header[0]->getName())->toBe('create');
    expect($header[1]->getLabel())->toBe(__('capell-admin::exchanger.import.action_label'));
    expect($header[1]->getColor())->toBe('gray');
    expect(array_keys($header[1]->getFlatActions()))->toBe(['allowed']);

    $emptyActions = $instance->getTable()->getEmptyStateActions();
    $this->assertInstanceOf(Action::class, $emptyActions[0]);
    $this->assertInstanceOf(ActionGroup::class, $emptyActions[1]);
    expect($emptyActions[0]->getName())->toBe('create');
    expect(array_keys($emptyActions[1]->getFlatActions()))->toBe(['allowed']);
})->group('blog-editorial-extensions');
