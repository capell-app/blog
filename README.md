# Blog

Status: **Available, schema-owning** · Kind: **package** · Tier: **free** · Bundle: **foundation** · Contexts: **admin, frontend, console** · Product group: **Capell Foundation**

## What This Plugin Adds

Blog adds article publishing, archive pages, tag pages, article widgets, sitemaps, and frontend Livewire page components to Capell.

- Article Filament resource.
- Blog, archive, and tag frontend Livewire components.
- Article widgets and configurators for Mosaic.
- Sitemap extensions for articles, archives, and tags.
- Commands to install and create blog pages.

## Why It Matters

**For developers:** Builds on core pages, layouts, translations, page URLs, Mosaic widgets, and tags while keeping article-specific logic in actions and loaders.

**For teams:** Gives editors a dedicated article workflow that still fits the same structured publishing foundation as pages.

## Screens And Workflow

Screenshots are generated from [docs/screenshots.json](docs/screenshots.json) during package deployment.

- Articles admin index.
- Create/edit article form.
- Blog page frontend output.
- Archive page frontend output.
- Tag page frontend output.

## Technical Shape

- BlogServiceProvider, AdminServiceProvider, ConsoleServiceProvider, and FrontendServiceProvider register package surfaces.
- Migration creates articles.
- Model: Article.
- Filament resource: ArticleResource.
- Livewire pages: Blog, Archive, Tag.
- Listeners sync navigation and translation changes.

## Data Model

- articles stores uuid, workspace, type, layout, site, meta, visible_from, and visible_until.
- Articles connect to sites, types, layouts, page URLs, translations, Mosaic widget assets, and tags.
- Blog requires Mosaic before install.
- Deletion and retention behaviour should be verified against the host application policy.

## Install Impact

- Adds articles table and article admin resource.
- Adds blog frontend components and sitemap extensions.
- Adds console commands for setup, install, demo, faker, and page creation.
- Requires Mosaic package first.
- May add blog pages to navigation through listener behaviour.

## Commands

- `capell:blog-create-pages {site : The ID of the site to create blog pages for}` (packages/blog/src/Console/Commands/CreateBlogPagesCommand.php)
- `capell:blog-demo {--sites=} {--user=} {--limit=}` (packages/blog/src/Console/Commands/DemoCommand.php)
- `capell:blog-faker {--count=25} {--sites=} {--languages=} {--force}` (packages/blog/src/Console/Commands/FakerCommand.php)
- `capell:blog-install` (packages/blog/src/Console/Commands/InstallCommand.php)
- `capell:blog-setup {--user= : Ignored — accepted for compatibility with capell:install} {--sites= : Ignored — accepted for compatibility with capell:install} {--languages= : Ignored — accepted for compatibility with capell:install} {--url= : Ignored — accepted for compatibility with capell:install}` (packages/blog/src/Console/Commands/SetupCommand.php)

## Admin And Access

- ArticleResource (packages/blog/src/Filament/Resources/Articles/ArticleResource.php, slug `article`)
- CreateArticle (packages/blog/src/Filament/Resources/Articles/Pages/CreateArticle.php)
- EditArticle (packages/blog/src/Filament/Resources/Articles/Pages/EditArticle.php)
- ListArticles (packages/blog/src/Filament/Resources/Articles/Pages/ListArticles.php)

- Gate: ArticleHealthWidgetAbstract: `developer`, `admin`, `super_admin`
- Gate: TopPagesWidgetAbstract: `admin`, `super_admin`
- Gate: TrafficChartWidgetAbstract: `admin`, `super_admin`

## Common Pitfalls

- Install Mosaic first.
- Run the package setup before expecting archive/tag pages.
- Check layouts before creating article records.
- Cache and sitemap output may need regeneration after setup.

## Quick Start

1. Install the package with `composer require capell-app/blog`.
2. Run the package migrations or the Capell package installer required by the host app.
3. Open the new admin or frontend surface and verify the result.

## Next Steps

- [docs/overview.md](docs/overview.md)
- [../mosaic/README.md](../mosaic/README.md)
- [../tags/README.md](../tags/README.md)
- [../seo-tools/README.md](../seo-tools/README.md)
