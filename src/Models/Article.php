<?php

declare(strict_types=1);

namespace Capell\Blog\Models;

use ArrayAccess;
use Bkwld\Cloner\Cloneable;
use Capell\Blog\Actions\ClearBlogContentCacheAction;
use Capell\Blog\Database\Factories\ArticleFactory;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Observers\ArticleObserver;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Core\Concerns\HasCapellMedia;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Enums\BlueprintGroupEnum;
use Capell\Core\Enums\BlueprintSubjectEnum;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Enums\PageOrderEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Concerns\CloneableExcept;
use Capell\Core\Models\Concerns\HasAssets;
use Capell\Core\Models\Concerns\HasMetaData;
use Capell\Core\Models\Concerns\HasMorphModelRelations;
use Capell\Core\Models\Concerns\HasPageOrdering;
use Capell\Core\Models\Concerns\HasPublishDates;
use Capell\Core\Models\Concerns\HasTranslations;
use Capell\Core\Models\Concerns\HasType;
use Capell\Core\Models\Concerns\HasTypes;
use Capell\Core\Models\Concerns\HasUserstamps;
use Capell\Core\Models\Contracts\Publishable;
use Capell\Core\Models\Contracts\Translatable;
use Capell\Core\Models\Contracts\Typeable;
use Capell\Core\Models\Contracts\Userstampable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\PublishingStudio\BelongsToWorkspace;
use Capell\Tags\Models\Concerns\HasTags;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;
use Staudenmeir\EloquentJsonRelations\Relations\BelongsToJson;

/**
 * @method HasOne|MorphOne translation()
 */
#[ObservedBy(ArticleObserver::class)]
class Article extends Model implements HasMedia, Pageable, Publishable, Translatable, Typeable, Userstampable
{
    use BelongsToWorkspace;
    use Cloneable;
    use CloneableExcept;
    use HasAssets;
    use HasCapellMedia;

    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    use HasJsonRelationships;
    use HasMetaData;
    use HasMorphModelRelations;
    use HasPageOrdering;
    use HasPublishDates;
    use HasTags;
    use HasTranslations;
    use HasType;
    use HasTypes;
    use HasUserstamps;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'articles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'layout_id',
        'meta',
        'name',
        'order',
        'uuid',
        'visible_from',
        'visible_until',
        'site_id',
        'blueprint_id',
    ];

    protected array $clone_exempt_attributes = [
        'hidden',
    ];

    protected static string $factory = ArticleFactory::class;

    public static function getDefaultType(?string $group): ?Blueprint
    {
        return Blueprint::query()
            ->where('type', BlueprintSubjectEnum::Page)
            ->when(
                $group !== null,
                fn (Builder $query): Builder => in_array($group, ['page', 'default'], true)
                    ? $query->where(
                        fn (Builder $query): Builder => $query
                            ->whereNull('group')
                            ->orWhereIn('group', [
                                BlueprintGroupEnum::Default->value,
                                BlueprintGroupEnum::System->value,
                            ]),
                    )
                    : $query->where('group', $group),
            )
            ->where('key', BlogPageTypeEnum::Article->value)
            ->orderBy('order')
            ->orderBy('default', 'desc')
            ->orderBy('name')
            ->first();
    }

    public static function hasPageHierarchy(): bool
    {
        return false;
    }

    public static function defaultOrdering(): PageOrderEnum
    {
        return PageOrderEnum::Latest;
    }

    public function shouldLogVisit(): bool
    {
        return (bool) ($this->type?->meta['disable_visit_logs'] ?? true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('article')
            ->logAll()
            ->logExcept([
                'updated_at',
                'created_at',
                'deleted_at',
                'workspace_id',
                'shadowed_by_workspace_id',
                'created_by',
                'updated_by',
                'deleted_by',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollectionEnum::Image->value)->singleFile();
    }

    public function getParentUrl(Language $language, bool $fullUrl = false): string
    {
        $url = $fullUrl ? $this->site->getSiteDomainUrl($language) : '/';

        return $url . BlogLoader::getBlogPageUrl(site: $this->site, language: $language, fullUrl: $fullUrl);
    }

    public function getPublishDate(): ?CarbonImmutable
    {
        $date = $this->visible_from ?? $this->created_at;

        return $date !== null ? CarbonImmutable::make($date) : null;
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }

    public function translation(): HasOne|MorphOne
    {
        $relation = $this->morphOne(Translation::class, 'translatable');

        if (method_exists($relation, 'chaperone')) {
            $relation->chaperone('translatable');
        }

        return $relation;
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return MorphOne<PageUrl, $this> */
    public function pageUrl(): MorphOne
    {
        return $this->morphOne(PageUrl::class, 'pageable')->withDefault(['site_id' => $this->site_id]);
    }

    /** @return MorphMany<PageUrl, $this> */
    public function pageUrls(): MorphMany
    {
        $model = $this->morphMany(PageUrl::class, 'pageable');

        if (method_exists($model, 'chaperone')) {
            $model->chaperone('pageable');
        }

        return $model;
    }

    /** @return MorphMany<Article, $this> */
    public function canonicalPages(): MorphMany
    {
        return $this->morphMany(
            self::class,
            'canonical_pageable',
            'meta->canonical_pageable_type',
            'meta->canonical_pageable_id',
        );
    }

    public function image(): MorphOne
    {
        return $this->morphOneMedia(MediaCollectionEnum::Image->value);
    }

    public function syncTags(string|array|ArrayAccess $tags): static
    {
        if (is_string($tags)) {
            $tags = Arr::wrap($tags);
        }

        $className = static::getTagClassName();
        $tagRecords = collect($className::findOrCreate($tags));

        $this->tags()->sync($tagRecords->pluck('id')->toArray());
        $this->clearBlogContentCache();

        return $this;
    }

    public function syncTagsWithType(array|ArrayAccess $tags, ?string $type = null): static
    {
        $className = static::getTagClassName();

        if ($this->languages->isNotEmpty()) {
            $tagRecords = collect();

            $this->languages->each(function (Language $language) use (&$tagRecords, &$tags, $className, $type): void {
                $tagRecords->push($className::findOrCreate($tags, $type, $language->code));
            });

            $tags = $tagRecords->flatten();
        } else {
            $tags = collect($className::findOrCreate($tags, $type));
        }

        $this->syncTagIds($tags->pluck('id')->toArray(), $type);
        $this->clearBlogContentCache();

        return $this;
    }

    /**
     * @return BelongsToJson<Article, $this>
     */
    public function related(): BelongsToJson
    {
        return $this->belongsToJson(self::class, 'meta->related');
    }

    public function canonicalPage(): MorphTo
    {
        return $this->morphTo(type: 'meta->canonical_pageable_type', id: 'meta->canonical_pageable_id');
    }

    public function draftRevisions(): HasMany
    {
        return $this->hasMany(self::class, 'id', 'id')->whereRaw('0=1');
    }

    /** @return Builder<self> */
    public function nextSiblings(): Builder
    {
        $effectivePublishDateExpression = $this->effectivePublishDateExpression();
        $currentPublishDate = $this->visible_from ?? $this->created_at;

        return self::query()
            ->whereKeyNot($this->getKey())
            ->where('site_id', $this->site_id)
            ->where(function (Builder $query) use ($effectivePublishDateExpression, $currentPublishDate): void {
                $query->whereRaw($effectivePublishDateExpression . ' > ?', [$currentPublishDate])
                    ->orWhere(function (Builder $query) use ($effectivePublishDateExpression, $currentPublishDate): void {
                        $query->whereRaw($effectivePublishDateExpression . ' = ?', [$currentPublishDate])
                            ->where('id', '>', $this->getKey());
                    });
            })
            ->orderByRaw($effectivePublishDateExpression . ' asc')
            ->orderBy('id');
    }

    /** @return Builder<self> */
    public function prevSiblings(): Builder
    {
        $effectivePublishDateExpression = $this->effectivePublishDateExpression();
        $currentPublishDate = $this->visible_from ?? $this->created_at;

        return self::query()
            ->whereKeyNot($this->getKey())
            ->where('site_id', $this->site_id)
            ->where(function (Builder $query) use ($effectivePublishDateExpression, $currentPublishDate): void {
                $query->whereRaw($effectivePublishDateExpression . ' < ?', [$currentPublishDate])
                    ->orWhere(function (Builder $query) use ($effectivePublishDateExpression, $currentPublishDate): void {
                        $query->whereRaw($effectivePublishDateExpression . ' = ?', [$currentPublishDate])
                            ->where('id', '<', $this->getKey());
                    });
            })
            ->orderByRaw($effectivePublishDateExpression . ' desc')
            ->orderBy('id', 'desc');
    }

    #[Override]
    protected static function booted(): void
    {
        static::creating(function (self $article): void {
            if ($article->uuid === null || $article->uuid === '') {
                $article->uuid = Str::uuid()->toString();
            }
        });
    }

    /** @return array<string, mixed>|null */
    protected function getUrlParamsAttribute(): ?array
    {
        return $this->type->meta['url_params'] ?? null;
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'meta' => 'json',
            'visible_from' => 'datetime',
            'visible_until' => 'datetime',
        ];
    }

    private function effectivePublishDateExpression(): string
    {
        return sprintf('COALESCE(%s, %s)', $this->qualifyColumn('visible_from'), $this->qualifyColumn('created_at'));
    }

    private function clearBlogContentCache(): void
    {
        if ($this->exists) {
            ClearBlogContentCacheAction::run($this);
        }
    }
}
