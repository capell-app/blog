<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Data\BlogTagLinkData;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Models\Translation;
use Capell\Tags\Models\Tag;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as SupportCollection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildBlogFeedXmlAction
{
    use AsFake;
    use AsObject;

    public function handle(SiteDomain $domain, string $format = 'rss', int $limit = 20, ?Tag $tag = null): string
    {
        $site = $domain->site;
        $language = $domain->language;

        if (! $site instanceof Site || ! $language instanceof Language) {
            return $this->emptyFeed($domain, $format, $tag);
        }

        /** @var EloquentCollection<int, Article> $articles */
        $articles = Article::query()
            ->with([
                'translation' => static function (Relation $query) use ($language): void {
                    $query->where('language_id', $language->id);
                },
                'pageUrl' => static function (Relation $query) use ($language, $site): void {
                    $query->where('language_id', $language->id)
                        ->where('site_id', $site->id)
                        ->where('status', true);
                },
            ])
            ->where('site_id', $site->id)
            ->whereHas('translation', fn (Builder $query): Builder => $query->where('language_id', $language->id))
            ->whereHas('pageUrl', fn (Builder $query): Builder => $query
                ->where('language_id', $language->id)
                ->where('site_id', $site->id)
                ->where('status', true))
            ->when(
                $tag instanceof Tag,
                fn (Builder $query): Builder => $query->whereHas(
                    'tags',
                    fn (Builder $tagQuery): Builder => $tagQuery->whereKey($tag?->getKey()),
                ),
            )
            ->publishedDate()
            ->publishedLatest()
            ->limit(max(1, min(100, $limit)))
            ->get();

        $articles->each(function (Article $article) use ($domain): void {
            $pageUrl = $article->pageUrl;

            if ($pageUrl instanceof PageUrl) {
                $pageUrl->setRelation('siteDomain', $domain);
            }
        });

        return $format === 'atom'
            ? $this->atom($domain, $articles, $tag)
            : $this->rss($domain, $articles, $tag);
    }

    private function emptyFeed(SiteDomain $domain, string $format, ?Tag $tag = null): string
    {
        return $format === 'atom'
            ? $this->atom($domain, new EloquentCollection, $tag)
            : $this->rss($domain, new EloquentCollection, $tag);
    }

    /**
     * @param  EloquentCollection<int, Article>  $articles
     */
    private function rss(SiteDomain $domain, EloquentCollection $articles, ?Tag $tag = null): string
    {
        $title = $this->feedTitle($domain, $tag);
        $feedUrl = $this->feedUrl($domain, 'xml', $tag);
        $updatedAt = $this->updatedAt($articles);

        $items = $articles
            ->map(fn (Article $article): string => $this->rssItem($article))
            ->implode("\n");

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>{$this->escape($title)}</title>
    <link>{$this->escape($this->channelUrl($domain, $tag))}</link>
    <description>{$this->escape($title . ' latest articles')}</description>
    <lastBuildDate>{$this->escape($updatedAt->toRfc2822String())}</lastBuildDate>
    <atom:link xmlns:atom="http://www.w3.org/2005/Atom" href="{$this->escape($feedUrl)}" rel="self" type="application/rss+xml" />
{$items}
  </channel>
</rss>
XML;
    }

    private function rssItem(Article $article): string
    {
        $translation = $article->translation;
        $pageUrl = $article->pageUrl;
        $url = $pageUrl instanceof PageUrl ? $pageUrl->full_url : '';
        $publishedAt = $article->getPublishDate() ?? $article->created_at;
        $description = ResolveArticleExcerptAction::run($translation instanceof Translation ? $translation : null);

        return <<<XML
    <item>
      <title>{$this->escape($translation instanceof Translation ? (string) $translation->title : $article->name)}</title>
      <link>{$this->escape($url)}</link>
      <guid isPermaLink="true">{$this->escape($url)}</guid>
      <pubDate>{$this->escape($this->date($publishedAt)->toRfc2822String())}</pubDate>
      <description>{$this->escape($description)}</description>
    </item>
XML;
    }

    /**
     * @param  EloquentCollection<int, Article>  $articles
     */
    private function atom(SiteDomain $domain, EloquentCollection $articles, ?Tag $tag = null): string
    {
        $title = $this->feedTitle($domain, $tag);
        $feedUrl = $this->feedUrl($domain, 'atom', $tag);
        $updatedAt = $this->updatedAt($articles);

        $entries = $articles
            ->map(fn (Article $article): string => $this->atomEntry($article))
            ->implode("\n");

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>{$this->escape($title)}</title>
  <link href="{$this->escape($this->channelUrl($domain, $tag))}" />
  <link href="{$this->escape($feedUrl)}" rel="self" type="application/atom+xml" />
  <id>{$this->escape($feedUrl)}</id>
  <updated>{$this->escape($updatedAt->toAtomString())}</updated>
{$entries}
</feed>
XML;
    }

    private function atomEntry(Article $article): string
    {
        $translation = $article->translation;
        $pageUrl = $article->pageUrl;
        $url = $pageUrl instanceof PageUrl ? $pageUrl->full_url : '';
        $publishedAt = $this->date($article->getPublishDate() ?? $article->created_at);
        $summary = ResolveArticleExcerptAction::run($translation instanceof Translation ? $translation : null);

        return <<<XML
  <entry>
    <title>{$this->escape($translation instanceof Translation ? (string) $translation->title : $article->name)}</title>
    <link href="{$this->escape($url)}" />
    <id>{$this->escape($url)}</id>
    <updated>{$this->escape($publishedAt->toAtomString())}</updated>
    <summary>{$this->escape($summary)}</summary>
  </entry>
XML;
    }

    /**
     * @param  EloquentCollection<int, Article>  $articles
     */
    private function updatedAt(EloquentCollection $articles): CarbonInterface
    {
        $article = $articles->first();

        return $article instanceof Article
            ? $this->date($article->getPublishDate() ?? $article->updated_at ?? $article->created_at)
            : now();
    }

    private function date(mixed $date): CarbonInterface
    {
        if ($date instanceof CarbonInterface) {
            return $date;
        }

        return now();
    }

    private function feedTitle(SiteDomain $domain, ?Tag $tag = null): string
    {
        $site = $domain->site;
        $title = ($site instanceof Site ? $site->title : config('app.name', 'Capell')) . ' Blog';

        if (! $tag instanceof Tag) {
            return $title;
        }

        $tagName = $this->tagName($domain, $tag);

        if ($tagName === '') {
            return $title;
        }

        return (string) trans('capell-blog::generic.tag_feed_title', [
            'feed_title' => $title,
            'tag_name' => $tagName,
        ]);
    }

    private function tagName(SiteDomain $domain, Tag $tag): string
    {
        $language = $domain->language;
        $name = $language instanceof Language
            ? $tag->getTranslation('name', $language->code)
            : null;

        return is_string($name) ? $name : '';
    }

    private function tagSlug(SiteDomain $domain, Tag $tag): string
    {
        $language = $domain->language;

        if (! $language instanceof Language) {
            return '';
        }

        $slug = $tag->getTranslations('slug')[$language->code] ?? '';

        return is_string($slug) ? $slug : '';
    }

    private function channelUrl(SiteDomain $domain, ?Tag $tag = null): string
    {
        return $tag instanceof Tag
            ? $this->tagPageUrl($domain, $tag)
            : $this->blogUrl($domain);
    }

    /**
     * Human-readable tag page URL for the feed's non-self link.
     *
     * Falls back to the blog index whenever the tag results page or its
     * published page URL cannot be resolved; never to the feed URL itself.
     */
    private function tagPageUrl(SiteDomain $domain, Tag $tag): string
    {
        $site = $domain->site;
        $language = $domain->language;

        if (! $site instanceof Site || ! $language instanceof Language) {
            return $this->blogUrl($domain);
        }

        $tagPage = TagLoader::getTagResultsPage($site, $language);

        if (! $tagPage instanceof Page) {
            return $this->blogUrl($domain);
        }

        $tagPage = clone $tagPage;

        $tagPage->load([
            'pageUrl' => static function (Relation $query) use ($language, $site): void {
                $query->where('language_id', $language->id)
                    ->where('site_id', $site->id)
                    ->where('status', true);
            },
        ]);

        $pageUrl = $tagPage->pageUrl;

        if (! $pageUrl instanceof PageUrl || ! $pageUrl->exists) {
            return $this->blogUrl($domain);
        }

        $pageUrl->setRelation('siteDomain', $domain);

        $tags = (new SupportCollection)->push($tag);

        $link = BlogTagLinkData::collectionFromTags($tags, $tagPage, $language)[0] ?? null;

        if (! $link instanceof BlogTagLinkData || $link->url === '') {
            return $this->blogUrl($domain);
        }

        return $link->url;
    }

    private function blogUrl(SiteDomain $domain): string
    {
        $site = $domain->site;
        $language = $domain->language;

        if ($site instanceof Site && $language instanceof Language) {
            return rtrim($domain->root_url, '/') . BlogLoader::getBlogPageUrl($site, $language, false);
        }

        return rtrim($domain->full_url, '/');
    }

    private function feedUrl(SiteDomain $domain, string $extension, ?Tag $tag = null): string
    {
        $base = rtrim($domain->full_url, '/');

        return $tag instanceof Tag
            ? $base . '/blog/tag/' . $this->tagSlug($domain, $tag) . '/feed.' . $extension
            : $base . '/blog/feed.' . $extension;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
