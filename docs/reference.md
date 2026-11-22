# Blog reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/blog`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Blog is an **Available** Capell package in the **Capell Publishing** product group. It ships as `capell-app/blog` and extends these surfaces: admin, frontend, console.

- Status: Available
- Tier: free
- Bundle: publishing
- Composer package: `capell-app/blog`
- Namespace: `Capell\Blog`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\Blog\Providers\BlogServiceProvider`
- `Capell\Blog\Providers\MigrationMetadataServiceProvider`
- `Capell\Blog\Providers\ConsoleServiceProvider`
- `Capell\Blog\Providers\AdminServiceProvider`
- `Capell\Blog\Providers\FrontendServiceProvider`

### Migrations

- `packages/blog/database/migrations/2026_05_10_190842_01_create_articles_table.php`
- `packages/blog/database/migrations/2026_08_28_000003_change_article_visibility_to_datetime.php`

### Models

- `Article`

### Filament classes

- `ArticleSelect`
- `SettingsTab`
- `TagsInput`
- `ArticlePageConfigurator`
- `ArticleWidgetConfigurator`
- `RelatedWidgetConfigurator`
- `ArticleResource`
- `CreateArticle`
- `EditArticle`
- `ListArticles`
- `ArticleForm`
- `ArticlePagesTable`
- `ArticleHealthFilamentWidget`
- `ListArticlesFilamentWidget`
- `TopPagesFilamentWidget`
- `TrafficChartFilamentWidget`

### Livewire components

- `Archive`
- `Author`
- `Blog`
- `Tag`

### Policies

- `ArticlePolicy`

### Listeners

- `ArticleTranslationSavedListener`

### Actions

- `ApplyArchiveDateFilterAction`
- `ApplyPreferredLanguageOrderAction`
- `ApplyRelatedArticleRankingAction`
- `AssignExampleArticleImageAction`
- `AttachBlogPublishingSurfaceToNavigationAction`
- `BuildArticleMetaDataAction`
- `BuildBlogFeedXmlAction`
- `BuildBlogResultsViewDataAction`
- `BuildTagListingDataAction`
- `ClearBlogContentCacheAction`
- `ClearBlogTagCacheAction`
- `CreateBlogHeroDemoContentAction`
- `CreateBlogPagesAction`
- `EnsureArticlePublishingDefaultsAction`
- `EnsureBlogPublishingSurfaceAction`
- `GenerateArchiveUrlAction`
- `GenerateBlogAuthorUrlAction`
- `GetArticleLayoutAction`
- `InstallBlogPackageAction`
- `PrepareBlogFeedLinksAction`
- `PrepareBlogFooterRenderDataAction`
- `ProvisionBlogPublishingPagesAction`
- `ProvisionBlogPublishingWidgetsAction`
- `RedirectMergedTagSlugAction`
- `ResolveArticleCreateSiteAction`
- `ResolveArticleExcerptAction`
- `ResolveBlogAuthorBySlugAction`
- `ResolveEligibleArticleBlueprintAction`
- `SanitizeBlogHtmlAction`
- `SeedBlogPublishingSurfaceAction`
- `SeedBlogScreenshotFixtureAction`

### Data objects

- `ArchiveLinkData`
- `ArchiveMonthData`
- `ArticleMetaData`
- `ArticleNeighborLinkData`
- `ArticleTranslationCoverageData`
- `ArticleWidgetRenderData`
- `BlogAuthorData`
- `BlogFeedLinkData`
- `BlogPublishingSurfaceData`
- `BlogPublishingSurfaceRequestData`
- `BlogPublishingSurfaceResultData`
- `BlogResultItemData`
- `BlogResultsViewData`
- `BlogTagLinkData`
- `BlogWidgetContentData`
- `ArticleHealthData`
- `LanguageCoverageData`
- `TagCountData`
- `TopPageData`
- `TopPagesData`
- `TrafficChartData`
- `TrafficPointData`
- `TagListingData`

### Command signatures

- `capell:blog-create-pages`
- `capell:blog-demo`
- `capell:blog-faker`
- `capell:blog-install`
- `capell:blog-setup`
- `capell:hero-demo`

### Manifest action API

- `install: Capell\Blog\Actions\InstallBlogPackageAction`
- `sanitizeBlogHtml: Capell\Blog\Actions\SanitizeBlogHtmlAction`

### Console command classes

- `CreateBlogPagesCommand`
- `DemoCommand`
- `FakerCommand`
- `HeroDemoCommand`
- `InstallCommand`
- `SeedBlogScreenshotFixtureCommand`
- `SetupCommand`

### Manifest contributions

- `admin-resource: Capell\Blog\Manifest\BlogAdminResourcesContribution`
- `configurator: Capell\Blog\Manifest\BlogConfiguratorsContribution`
- `console-command: Capell\Blog\Manifest\BlogConsoleCommandsContribution`
- `frontend-component: Capell\Blog\Manifest\BlogFrontendComponentsContribution`
- `health-check: Capell\Blog\Health\BlogHealthCheck`
- `migration: Capell\Blog\Manifest\BlogMigrationsContribution`
- `model: Capell\Blog\Manifest\BlogModelsContribution`
- `page-type: Capell\Blog\Manifest\BlogPageTypesContribution`
- `page-variation: Capell\Blog\Manifest\BlogPageTypesContribution`
- `permission: Capell\Blog\Manifest\BlogPermissionsContribution`
- `render-hook: Capell\Blog\Manifest\BlogRenderHooksContribution`
- `route: Capell\Blog\Manifest\BlogRoutesContribution`

### Health checks

- `Capell\Blog\Health\BlogHealthCheck`

### Blade views

- `packages/blog/resources/views/components/article-meta.blade.php`
- `packages/blog/resources/views/components/asset-after-title.blade.php`
- `packages/blog/resources/views/components/footer/pages.blade.php`
- `packages/blog/resources/views/components/footer/tags.blade.php`
- `packages/blog/resources/views/components/page/author.blade.php`
- `packages/blog/resources/views/components/page/published-date.blade.php`
- `packages/blog/resources/views/components/page/tags.blade.php`
- `packages/blog/resources/views/components/tag.blade.php`
- `packages/blog/resources/views/components/widget/page/archives.blade.php`
- `packages/blog/resources/views/components/widget/page/article.blade.php`
- `packages/blog/resources/views/components/widget/tag/tags.blade.php`
- `packages/blog/resources/views/filament/widgets/article-health.blade.php`
- `packages/blog/resources/views/filament/widgets/top-pages.blade.php`
- `packages/blog/resources/views/filament/widgets/traffic-chart.blade.php`
- `packages/blog/resources/views/livewire/page/results-slot.blade.php`
- `packages/blog/resources/views/livewire/page/results.blade.php`

### Cache tags

- `blog`


## Data Model

- Required tables: `articles`.
- Models: `Article`.
- Core record references in migrations: `sites via site_id`, `layouts via layout_id`.
- Migration files: `2026_05_10_190842_01_create_articles_table.php`, `2026_08_28_000003_change_article_visibility_to_datetime.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare cascade-on-delete relationships; no timed pruning or retention schedule is declared in `capell.json`.

<!-- prettier-ignore-end -->
