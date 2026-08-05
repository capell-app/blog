# Blog

<!-- prettier-ignore-start -->

## What This Plugin Adds

Blog is an **Available**, **Schema-owning** Capell package in the **Capell Publishing** product group. It ships as `capell-app/blog` and extends these surfaces: admin, frontend, console.

Blog adds article publishing, archive and tag page types, related-article widgets, and RSS, Atom, and XML feed routes to Capell.

Editors draft, schedule, tag, and publish articles from ArticleResource. Visitors can browse published article, archive, and tag pages or subscribe to a feed.

Evidence: [`capell.json`](capell.json), [`src/Manifest/BlogPageTypesContribution.php`](src/Manifest/BlogPageTypesContribution.php), [`src/Manifest/BlogRoutesContribution.php`](src/Manifest/BlogRoutesContribution.php), [`docs/overview.admin.md`](docs/overview.admin.md), [`docs/screenshots.json`](docs/screenshots.json), [`tests/Feature/Pages/ArticlePageTest.php`](tests/Feature/Pages/ArticlePageTest.php), [`tests/Feature/BlogFeedTest.php`](tests/Feature/BlogFeedTest.php).

Status details:

- Status: Available
- Tier: free
- Bundle: publishing
- Composer package: `capell-app/blog`
- Namespace: `Capell\Blog`
- Theme key: not applicable

## Why It Matters

**For developers:** Blog registers the article page type, configurators, feed routes, and render-data Actions as package extension points instead of adding article behavior to core.

**For teams:** Editorial teams can keep work private as a draft, schedule publication, organize articles with tags, and reuse related content elsewhere on the site.

Evidence: [`capell.json`](capell.json), [`src/Filament/Configurators/Articles/ArticlePageConfigurator.php`](src/Filament/Configurators/Articles/ArticlePageConfigurator.php), [`src/Actions/BuildBlogFeedXmlAction.php`](src/Actions/BuildBlogFeedXmlAction.php), [`src/Actions/BuildBlogResultsViewDataAction.php`](src/Actions/BuildBlogResultsViewDataAction.php), [`docs/overview.admin.md`](docs/overview.admin.md), [`docs/screenshots.json`](docs/screenshots.json), [`tests/Feature/Filament/Resources/Article/Pages/EditArticleTest.php`](tests/Feature/Filament/Resources/Article/Pages/EditArticleTest.php).

## Screens And Workflow

Screenshot contract: `docs/screenshots.json`.

![Articles admin index](docs/screenshots/articles-admin-index.png)

![Create/edit article form](docs/screenshots/create-edit-article-form.png)

- Articles admin index (admin, required evidence).
- Create/edit article form (admin, required evidence).
- Blog page frontend output (frontend, supplementary evidence).
- Archive page frontend output (frontend, required evidence).
- Tag page frontend output (frontend, required evidence).

## Works With

- [Comments](../comments/README.md): optional integration backed by the interop evidence map.
- [Navigation](../navigation/README.md): optional integration backed by the interop evidence map.
- [Insights](../insights/README.md): optional integration backed by the interop evidence map.
- [Publishing Studio](../publishing-studio/README.md): optional integration backed by the interop evidence map.
- [Site Discovery](../site-discovery/README.md): optional integration backed by the interop evidence map.
- [Url Manager](../url-manager/README.md): optional integration backed by the interop evidence map.

## Technical Shape

- Service providers: `Capell\Blog\Providers\ConsoleServiceProvider`, `Capell\Blog\Providers\BlogServiceProvider`, `Capell\Blog\Providers\AdminServiceProvider`, `Capell\Blog\Providers\FrontendServiceProvider`.
- Migrations: `packages/blog/database/migrations/2026_05_10_190842_01_create_articles_table.php`.
- Models: `Article`.
- Filament classes: `ArticleSelect`, `SettingsTab`, `TagsInput`, `ArticlePageConfigurator`, `ArticleWidgetConfigurator`, `RelatedWidgetConfigurator`, `ArticleResource`, `CreateArticle`, `EditArticle`, `ListArticles`, `ArticleForm`, `ArticlePagesTable`, `and 4 more`.
- Livewire components: `Archive`, `Blog`, `Tag`.
- Policies: `ArticlePolicy`.
- Listeners: `AddBlogPagesToNavigation`, `ArticleTranslationSavedListener`.
- Actions: `ApplyArchiveDateFilterAction`, `ApplyPreferredLanguageOrderAction`, `AssignExampleArticleImageAction`, `AttachBlogPublishingSurfaceToNavigationAction`, `BuildArticleMetaDataAction`, `BuildBlogFeedXmlAction`, `BuildBlogResultsViewDataAction`, `BuildTagListingDataAction`, `ClearBlogContentCacheAction`, `ClearBlogTagCacheAction`, `CreateBlogHeroDemoContentAction`, `CreateBlogPagesAction`, `and 12 more`.
- Data objects: `ArchiveLinkData`, `ArchiveMonthData`, `ArticleMetaData`, `ArticleNeighborLinkData`, `ArticleWidgetRenderData`, `BlogPublishingSurfaceData`, `BlogPublishingSurfaceRequestData`, `BlogPublishingSurfaceResultData`, `BlogResultItemData`, `BlogResultsViewData`, `BlogTagLinkData`, `BlogWidgetContentData`, `and 8 more`.
- Command signatures: `capell:blog-demo`, `capell:blog-install`, `capell:blog-setup`.
- Manifest action API: `install: Capell\Blog\Actions\InstallBlogPackageAction`, `sanitizeBlogHtml: Capell\Blog\Actions\SanitizeBlogHtmlAction`.
- Console command classes: `CreateBlogPagesCommand`, `DemoCommand`, `FakerCommand`, `HeroDemoCommand`, `InstallCommand`, `SetupCommand`.
- Manifest contributions: `admin-resource: Capell\Blog\Manifest\BlogAdminResourcesContribution`, `configurator: Capell\Blog\Manifest\BlogConfiguratorsContribution`, `console-command: Capell\Blog\Manifest\BlogConsoleCommandsContribution`, `frontend-component: Capell\Blog\Manifest\BlogFrontendComponentsContribution`, `health-check: Capell\Blog\Health\BlogHealthCheck`, `migration: Capell\Blog\Manifest\BlogMigrationsContribution`, `model: Capell\Blog\Manifest\BlogModelsContribution`, `page-type: Capell\Blog\Manifest\BlogPageTypesContribution`, `page-variation: Capell\Blog\Manifest\BlogPageTypesContribution`, `permission: Capell\Blog\Manifest\BlogPermissionsContribution`, `render-hook: Capell\Blog\Manifest\BlogRenderHooksContribution`, `route: Capell\Blog\Manifest\BlogRoutesContribution`.
- Health checks: `Capell\Blog\Health\BlogHealthCheck`.
- Blade views: `packages/blog/resources/views/components/article-meta.blade.php`, `packages/blog/resources/views/components/asset-after-title.blade.php`, `packages/blog/resources/views/components/footer/pages.blade.php`, `packages/blog/resources/views/components/footer/tags.blade.php`, `packages/blog/resources/views/components/page/author.blade.php`, `packages/blog/resources/views/components/page/published-date.blade.php`, `packages/blog/resources/views/components/page/tags.blade.php`, `packages/blog/resources/views/components/tag.blade.php`, `packages/blog/resources/views/components/widget/page/archives.blade.php`, `packages/blog/resources/views/components/widget/page/article.blade.php`, `packages/blog/resources/views/components/widget/tag/tags.blade.php`, `packages/blog/resources/views/filament/widgets/article-health.blade.php`, `and 4 more`.
- Cache tags: `blog`.

## Data Model

- Required tables: `articles`.
- Models: `Article`.
- Core record references in migrations: `sites via site_id`, `layouts via layout_id`.
- Migration files: `2026_05_10_190842_01_create_articles_table.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare cascade-on-delete relationships; no timed pruning or retention schedule is declared in `capell.json`.

## Install Impact

- Required packages: `capell-app/admin`, `capell-app/content-sections`, `capell-app/core`, `capell-app/frontend`, `capell-app/html-cache`, `capell-app/layout-builder`, `capell-app/tags`.
- Admin navigation: declares `admin-resource: BlogAdminResourcesContribution`; each Filament page or resource controls its own navigation visibility.
- Admin/editor extensions: `configurator: BlogConfiguratorsContribution`.
- Permissions: `article.view`, `article.create`, `article.update`, `article.delete`, `article.restore`, `article.force_delete`, `tag.view`, `tag.create`, `tag.update`, `tag.delete`, `tag.restore`, `tag.force_delete`.
- Public routes: registers `BlogRoutesContribution`.
- Database changes: package migrations are declared.
- Config: no package config files.
- Settings: no package settings declared.
- Queues or schedules: none declared.
- Cache tags: `blog`.
- Commands: `capell:blog-demo`, `capell:blog-install`, `capell:blog-setup`.

## Common Pitfalls

- Install `capell-app/layout-builder` before Blog so its page types and widgets can register against the required editor surface.
- Run migrations before opening package resources or public routes.
- Keep public Blade and cached HTML free of authoring markers, model IDs, permissions, signed editor URLs, and lazy database queries.
- Custom write integrations must preserve invalidation for `blog` cache tags.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |
| Public output leaks unexpected state | Render data, cache variation, or authoring boundary has regressed | Check public Blade, cache tags, and public-output safety tests | Move data loading out of Blade and rerun the package public-output tests |

## Quick Start

1. Install the package: `composer require capell-app/blog`.
2. Run the required setup: `php artisan capell:blog-setup`.
3. Open a verified package admin surface and confirm Blog is available.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Admin guide](docs/admin-guide.md)
- [Troubleshooting](#troubleshooting)
- [Screenshot contract](docs/screenshots.json)
- [Marketplace assets](docs/assets/marketplace/)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Related packages: [Content Sections](../content-sections/README.md), [Html Cache](../html-cache/README.md), [Layout Builder](../layout-builder/README.md), [Tags](../tags/README.md), [Comments](../comments/README.md), [Navigation](../navigation/README.md), [Insights](../insights/README.md), [Publishing Studio](../publishing-studio/README.md), [Site Discovery](../site-discovery/README.md), [Url Manager](../url-manager/README.md).
- Focused tests: `vendor/bin/pest packages/blog/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
