<?php

declare(strict_types=1);

use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Blog\View\Components\AssetAfterTitle;
use Capell\Blog\View\Components\Footer\Tags as FooterTags;
use Capell\Blog\View\Components\Page\BeforeContentTags;
use Capell\Blog\View\Components\Widget\Page\Archives;
use Capell\Blog\View\Components\Widget\Page\Related;
use Capell\Blog\View\Components\Widget\Tag\Tags;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\LayoutCreator;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Frontend\Data\FrontendContext;
use Capell\Frontend\Data\FrontendRenderContextData;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Render\PublicViewQueryGuard;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Support\Creator\WidgetCreator;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tests\Support\Concerns\TestingFrontend;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

uses(TestingFrontend::class);

test('translated ordinary pages render layout tags without querying in Blade', function (?string $content, bool $translatedResults): void {
    config([
        'capell-frontend.public_view_query_guard.enabled' => true,
        'capell-frontend.public_view_query_guard.mode' => 'exception',
    ]);
    $english = Language::factory()->english()->create();
    $french = Language::factory()->french()->create();
    $site = Site::factory()->language($english)->withTranslations(
        [$english, $french],
        siteDomainData: ['domain' => 'localhost', 'path' => null],
    )->create();
    $creator = resolve(BlogCreator::class);
    // A newly translated site may still have English-only publishing pages.
    $creator->createTagPage($site, languages: collect($translatedResults ? [$english, $french] : [$english]));
    $tag = Tag::factory()->site($site)->type(TagTypeEnum::Page)->create([
        'name' => [$english->code => 'English topic', $french->code => 'Sujet français'],
        'slug' => [$english->code => 'english-topic', $french->code => 'sujet-francais'],
    ]);
    Article::factory()->site($site)->type($creator->createArticlePageType())
        ->withTranslations([$english, $french])->hasAttached($tag)->create();
    resolve(WidgetCreator::class)->pageContentWidget();
    $creator->createTagsWidget(collect([$english, $french]));
    $layout = resolve(LayoutCreator::class)->create(LayoutEnum::Default);
    $layout->forceFill(['containers' => [
        'main' => ['widgets' => [['widget_key' => 'page-content']]],
        'sidebar' => ['widgets' => [['widget_key' => 'tags']]],
    ]])->save();
    $page = Page::factory()->site($site)->layout($layout)->withTranslations(
        [$english, $french],
        data: ['title' => 'Translated ordinary page', 'content' => $content],
        slug: 'translated-page',
    )->create();
    $url = PageUrl::query()->where('pageable_id', $page->getKey())
        ->where('pageable_type', $page->getMorphClass())->where('language_id', $french->getKey())->firstOrFail();
    $bladeQueries = [];
    $guard = resolve(PublicViewQueryGuard::class);
    DB::listen(function (QueryExecuted $query) use ($guard, &$bladeQueries): void {
        if ($guard->isActive()) {
            $bladeQueries[] = $query->sql;
        }
    });

    $response = get($url->fullUrl() . '?without_html_cache=1')
        ->assertOk()
        ->assertSee('Translated ordinary page')
        ->assertSee($content ?? 'Translated ordinary page', false)
        ->assertDontSee('data-authoring', false)
        ->assertDontSee('data-editable', false)
        ->assertDontSee('signed-editor', false)
        ->assertDontSee('capell-blog::', false)
        ->assertDontSee('widget_key', false);

    expect($bladeQueries)->toBe([]);
    if ($translatedResults) {
        $response->assertSee('Sujet français')->assertSee('/fr/', false);
    } else {
        $response->assertDontSee('Sujet français');
    }
})->with(['empty content' => [null], 'non-empty content' => ['<p>Contenu français.</p>']])->with([
    'translated results page' => true,
    'untranslated results page' => false,
]);

test('Blog components preserve prepared null pages and empty collections during Blade', function (string $component): void {
    config([
        'capell-frontend.public_view_query_guard.enabled' => true,
        'capell-frontend.public_view_query_guard.mode' => 'exception',
    ]);
    $language = Language::factory()->french()->create();
    $site = Site::factory()->language($language)->create();
    $context = new FrontendContext($site, $language, new Page, null, null, [], null);
    app()->instance(FrontendContextReader::class, $context);
    Frontend::clearResolvedInstance(FrontendContextReader::class);
    $context->setFrontendData('blog.archive_page', null);
    $context->setFrontendData('blog.archives', collect());
    $context->setFrontendData('blog.tag_page', null);
    $context->setFrontendData('blog.sidebar_tags', collect());
    $context->setFrontendData('blog.related_articles', collect());
    $widget = new Widget;
    /** @var Collection<array-key, mixed> $tags */
    $tags = collect([new Tag(['name' => [$language->code => 'Unlinked topic']])]);
    $guard = resolve(PublicViewQueryGuard::class);
    $queries = [];
    DB::listen(function (QueryExecuted $query) use ($guard, &$queries): void {
        if ($guard->isActive()) {
            $queries[] = $query->sql;
        }
    });

    $html = $guard->guard(
        new FrontendRenderContextData(null, $site, $language, null, null),
        function () use ($component, $tags, $widget): string {
            $instance = match ($component) {
                'after title' => new AssetAfterTitle(tags: $tags),
                'before content' => new BeforeContentTags(null, $tags),
                'footer tags' => new FooterTags([]),
                'archives' => new Archives([], 'sidebar', 0, (object) ['index' => 0], $widget),
                'tags' => new Tags([], 'sidebar', 0, (object) ['index' => 0], $widget),
                'related' => new Related([], 'sidebar', 0, (object) ['index' => 0], $widget),
                default => throw new InvalidArgumentException('Unknown Blog component: ' . $component),
            };
            $view = $instance->render();

            if ($view instanceof View) {
                return $view->render();
            }

            if (! is_string($view)) {
                throw new LogicException('Expected the Blog component to render a view or string.');
            }

            return $view;
        },
    );

    expect($queries)->toBe([])
        ->and($html)->not->toContain('href=', 'Unlinked topic', 'widget_key', 'data-authoring');
})->with(['archives', 'tags', 'after title', 'before content', 'footer tags', 'related']);
