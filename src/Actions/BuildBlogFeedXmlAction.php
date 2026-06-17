<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Models\Article;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Core\Models\Language;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Models\Translation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildBlogFeedXmlAction
{
    use AsObject;

    public function handle(SiteDomain $domain, string $format = 'rss', int $limit = 20): string
    {
        $site = $domain->site;
        $language = $domain->language;

        if (! $site instanceof Site || ! $language instanceof Language) {
            return $this->emptyFeed($domain, $format);
        }

        /** @var EloquentCollection<int, Article> $articles */
        $articles = Article::query()
            ->with([
                'translation' => fn (MorphOne $query): MorphOne => $query->where('language_id', $language->id),
                'pageUrl' => fn (MorphOne $query): MorphOne => $query
                    ->where('language_id', $language->id)
                    ->where('site_id', $site->id)
                    ->where('status', true),
            ])
            ->where('site_id', $site->id)
            ->whereHas('translation', fn (Builder $query): Builder => $query->where('language_id', $language->id))
            ->whereHas('pageUrl', fn (Builder $query): Builder => $query
                ->where('language_id', $language->id)
                ->where('site_id', $site->id)
                ->where('status', true))
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
            ? $this->atom($domain, $articles)
            : $this->rss($domain, $articles);
    }

    private function emptyFeed(SiteDomain $domain, string $format): string
    {
        return $format === 'atom'
            ? $this->atom($domain, new EloquentCollection)
            : $this->rss($domain, new EloquentCollection);
    }

    /**
     * @param  EloquentCollection<int, Article>  $articles
     */
    private function rss(SiteDomain $domain, EloquentCollection $articles): string
    {
        $title = $this->feedTitle($domain);
        $feedUrl = $this->feedUrl($domain, 'xml');
        $updatedAt = $this->updatedAt($articles);

        $items = $articles
            ->map(fn (Article $article): string => $this->rssItem($article))
            ->implode("\n");

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>{$this->escape($title)}</title>
    <link>{$this->escape($this->blogUrl($domain))}</link>
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
        $url = $article->pageUrl?->full_url ?? '';
        $publishedAt = $article->getPublishDate() ?? $article->created_at;
        $description = $translation instanceof Translation ? (string) ($translation->summary ?? '') : '';

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
    private function atom(SiteDomain $domain, EloquentCollection $articles): string
    {
        $title = $this->feedTitle($domain);
        $feedUrl = $this->feedUrl($domain, 'atom');
        $updatedAt = $this->updatedAt($articles);

        $entries = $articles
            ->map(fn (Article $article): string => $this->atomEntry($article))
            ->implode("\n");

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>{$this->escape($title)}</title>
  <link href="{$this->escape($this->blogUrl($domain))}" />
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
        $url = $article->pageUrl?->full_url ?? '';
        $publishedAt = $this->date($article->getPublishDate() ?? $article->created_at);
        $summary = $translation instanceof Translation ? (string) ($translation->summary ?? '') : '';

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

    private function feedTitle(SiteDomain $domain): string
    {
        $site = $domain->site;

        return ($site instanceof Site ? $site->title : config('app.name', 'Capell')) . ' Blog';
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

    private function feedUrl(SiteDomain $domain, string $extension): string
    {
        return rtrim($domain->full_url, '/') . '/blog/feed.' . $extension;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
