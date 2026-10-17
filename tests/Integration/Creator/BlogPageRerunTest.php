<?php

declare(strict_types=1);

use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Enums\UrlTypeEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\SiteDomain;

it('keeps a site-owned redirect on the blog URL when blog setup reruns', function (): void {
    $siteDomain = SiteDomain::factory()->default()->create();
    $site = $siteDomain->site;
    $blogCreator = resolve(BlogCreator::class);

    $blogPage = $blogCreator->createBlogPage($site);
    $blogUrl = PageUrl::query()->where('site_id', $site->id)->where('url', '/blog')->sole();

    // The site retires the blog page URL and redirects /blog elsewhere.
    $blogUrl->forceFill([
        'pageable_type' => null,
        'pageable_id' => null,
        'target_url' => '/newsletter',
        'status_code' => 301,
        'is_manual' => true,
        'type' => UrlTypeEnum::Redirect,
    ])->save();

    $rerunPage = $blogCreator->createBlogPage($site);

    expect($rerunPage->is($blogPage))->toBeTrue()
        ->and(PageUrl::query()->where('site_id', $site->id)->where('url', '/blog')->sole())
        ->type->toBe(UrlTypeEnum::Redirect)
        ->target_url->toBe('/newsletter');
});

it('preserves an edited blog translation when blog setup reruns', function (): void {
    $site = SiteDomain::factory()->default()->create()->site;
    $blogCreator = resolve(BlogCreator::class);
    $blogPage = $blogCreator->createBlogPage($site);
    $translation = $blogPage->translations()->firstOrFail();
    $translation->forceFill(['title' => 'Journal'])->save();

    $blogCreator->createBlogPage($site);

    expect($translation->refresh()->title)->toBe('Journal')
        ->and($translation->meta)->toMatchArray(['slug' => 'blog']);
});

it('repairs a missing canonical blog URL even when its translation is unchanged', function (): void {
    $site = SiteDomain::factory()->default()->create()->site;
    $creator = resolve(BlogCreator::class);
    $page = $creator->createBlogPage($site);
    $translation = $page->translations()->sole();
    $original = $translation->getAttributes();
    $page->pageUrls()->forceDelete();

    $creator->createBlogPage($site);

    expect($page->pageUrls()->sole())
        ->url->toBe('/blog')
        ->type->toBeNull()
        ->language_id->toBe($translation->language_id)
        ->and($translation->refresh()->getAttributes())->toBe($original);
});

it('fills missing blog defaults without reclaiming a detached redirect', function (bool $missingSlug): void {
    $site = SiteDomain::factory()->default()->create()->site;
    $creator = resolve(BlogCreator::class);
    $page = $creator->createBlogPage($site);
    $translation = $page->translations()->sole();
    $translation->forceFill([
        'title' => 'Journal',
        'content' => '<p>Site-owned introduction.</p>',
        'meta' => ['hero_title' => null, ...($missingSlug ? [] : ['slug' => 'blog'])],
    ])->saveQuietly();
    $redirect = $page->pageUrls()->sole();
    $redirect->forceFill([
        'pageable_type' => null,
        'pageable_id' => null,
        'target_url' => '/newsletter',
        'status_code' => 301,
        'is_manual' => true,
        'type' => UrlTypeEnum::Redirect,
    ])->save();
    $originalRedirect = $redirect->refresh()->getAttributes();

    $creator->createBlogPage($site);

    expect($translation->refresh())
        ->title->toBe('Journal')
        ->content->toBe('<p>Site-owned introduction.</p>')
        ->meta->toBe([
            'hero_title' => null,
            'label' => __('capell-blog::generic.blog'),
            'no_results' => __('capell-blog::messages.no_articles_found'),
            'slug' => 'blog',
        ])
        ->and($redirect->refresh()->getAttributes())->toBe($originalRedirect)
        ->and($page->pageUrls()->count())->toBe(0);
})->with(['missing labels' => false, 'missing labels and slug' => true]);

it('adds a blog language and repairs free URLs alongside a detached redirect', function (): void {
    $site = SiteDomain::factory()->default()->create()->site;
    $creator = resolve(BlogCreator::class);
    $page = $creator->createBlogPage($site);
    $redirect = $page->pageUrls()->sole();
    $redirect->forceFill([
        'pageable_type' => null,
        'pageable_id' => null,
        'target_url' => '/newsletter',
        'status_code' => 301,
        'is_manual' => true,
        'type' => UrlTypeEnum::Redirect,
    ])->save();
    $originalRedirect = $redirect->refresh()->getAttributes();
    $welsh = Language::factory()->create(['code' => 'cy', 'name' => 'Welsh']);
    SiteDomain::factory()->site($site)->language($welsh)->create(['path' => '/cy']);
    $site->unsetRelation('siteDomains');
    $languages = $site->getAllLanguages();

    $creator->createBlogPage($site, languages: $languages);

    expect($page->translations()->count())->toBe(2)
        ->and(blogTestTranslation($page->translations()->where('language_id', $welsh->id)->sole())->meta)
        ->toMatchArray(['slug' => 'blog', 'label' => __('capell-blog::generic.blog')])
        ->and($page->pageUrls()->sole())
        ->language_id->toBe($welsh->id)
        ->url->toBe('/blog')
        ->type->toBeNull()
        ->and($redirect->refresh()->getAttributes())->toBe($originalRedirect);

    $page->pageUrls()->forceDelete();
    $creator->createBlogPage($site, languages: $languages);

    expect($page->pageUrls()->sole())
        ->language_id->toBe($welsh->id)
        ->url->toBe('/blog')
        ->type->toBeNull()
        ->and($redirect->refresh()->getAttributes())->toBe($originalRedirect);
});

it('keeps fresh blog installation defaults and canonical URL unchanged', function (): void {
    $site = SiteDomain::factory()->default()->create()->site;
    $page = resolve(BlogCreator::class)->createBlogPage($site);

    expect($page->translations()->sole())
        ->title->toBe(__('capell-blog::generic.blog'))
        ->content->toBe('')
        ->meta->toBe([
            'hero_title' => __('capell-blog::generic.blog'),
            'label' => __('capell-blog::generic.blog'),
            'no_results' => __('capell-blog::messages.no_articles_found'),
            'slug' => 'blog',
        ])
        ->and($page->pageUrls()->sole())
        ->language_id->toBe($site->language_id)
        ->url->toBe('/blog')
        ->type->toBeNull();
});
