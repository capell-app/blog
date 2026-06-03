# Blog — Improvement & Growth Plan
> Package: capell-app/blog · Kind: package · Tier: free · Product group: Capell Foundation · Bundle: foundation · Status: Draft

## 1. Snapshot

Blog is a foundation/free, schema-owning package that adds the editorial publishing layer to Capell: an `articles` table + `Article` model, a Filament `ArticleResource` (admin), three Livewire frontend page types (`Blog`, `Archive`, `Tag` in `src/Livewire/Page`), article/related/archives/tags layout widgets, dashboard widgets (article health, top pages, traffic chart), and Site Discovery sitemap contributions (`src/Support/Sitemap`). Surfaces declared: `admin`, `frontend`, `console`. Domain logic is correctly factored into `src/Actions` and `src/Support/Loader` (`BlogLoader`, `TagLoader`); public Blade receives hydrated Data objects and does not query the DB directly. It is one of the heaviest-dependency packages in the foundation tier, requiring 11 sibling Capell packages (`admin`, `content-sections`, `frontend`, `html-cache`, `insights`, `layout-builder`, `navigation`, `publishing-studio`, `site-discovery`, `tags`, plus `core` via the manifest).

Current marketplace summary (verbatim): *"Blog adds article publishing, archive pages, tag pages, article widgets, Site Discovery sitemap contributions, and frontend Livewire page components to Capell."*

Screenshot count: manifest `marketplace.screenshots[]` declares **1** entry (`docs/assets/marketplace/extension-card.jpg`). The repo actually ships **10** committed PNG screenshots under `docs/screenshots/` (catalogued in `docs/screenshots.json`) plus **3** marketplace JPGs (`extension-card.jpg`, `hero-desktop.jpg`, `hero-mobile.jpg`). **Mismatch**: 13 committed images vs 1 declared, and `docs/overview.md` itself warns the frontend captures are "blank captures" not yet usable as documentation assets.

## 2. Improvements (existing functionality)

- **Implement the four declared health checks** — `capell.json` declares 4 checks (2 `critical`: `blog.publishing-surface`, `blog.cache-invalidation`; 2 `warning`: `blog.author-related-rendering`, `blog.sitemap-static-export`) all routed to `Capell\Blog\Health\BlogHealthCheck`, but that class implements only `compatibleCapellApiVersion(): string` and contains zero check logic. The manifest advertises critical health coverage that does not exist. — `src/Health/BlogHealthCheck.php` — M
- **Resolve the dual install/setup action split** — `InstallBlogPackageAction` (manifest `actions.install`, runs migrations + `AssignPermissionsToRole` + filament assets) and `InstallPackageAction` (an `AsFake`/`AsObject` action wired into `SetupCommand`, runs `EnsureArticlePublishingDefaultsAction` + `EnsureBlogPublishingSurfaceAction` per site) are two separate entry points with near-identical names. The generic name `InstallPackageAction` invites confusion with the same-named class in other packages (tests already alias `LayoutBuilder\Actions\InstallPackageAction`). Rename to intent (e.g. `SeedBlogPublishingSurfaceAction`) and document the install-vs-setup boundary. — `src/Actions/InstallPackageAction.php`, `src/Console/Commands/SetupCommand.php` — S
- **Fix the orphaned/mislabelled `ArticleTypeFactory`** — `database/factories/ArticleTypeFactory.php` extends `BlueprintFactory`, but there is no `ArticleType` model and `articles` keys off `blueprint_id` (no `type_id`). The factory name implies a type model that does not exist; either rename it to reflect that it builds article blueprints, or remove it if unused. — `database/factories/ArticleTypeFactory.php` — S
- **Populate the empty manifest metadata arrays** — `capabilities: []`, `settings: []`, and `permissions: []` are all empty, yet the package ships real per-role gates (`docs/overview.md`: `ArticleHealthWidget` → developer/admin/super_admin; `TopPages`/`TrafficChart` → admin/super_admin) and `InstallBlogPackageAction` calls `AssignPermissionsToRole` for `ResourceEnum::cases()`. The manifest under-declares its own surface, so marketplace tooling/health/permission audits see nothing. — `capell.json` — S
- **Reconcile screenshots into the manifest** — promote the usable admin captures from `docs/screenshots.json` (10 entries) into `marketplace.screenshots[]` (currently 1), and replace or regenerate the blank frontend captures that `docs/overview.md` flags as not-yet-usable. — `capell.json`, `docs/screenshots.json` — S
- **Fix the `ListArtcles` typo** — the widget test `tests/Feature/Filament/Widgets/ListArtclesWidgetTest.php` and the underlying widget class carry a misspelling (`Artcles`). Cosmetic but ships in a first-party foundation package. — `tests/Feature/Filament/Widgets/ListArtclesWidgetTest.php` — S
- **Register a cache-invalidation dependency** — `capell.json` `performance.cacheSafety.invalidationSources: []` is empty and there is no `CacheInvalidationRegistry::registerDependency()` call anywhere in `src`. Invalidation is instead driven imperatively via model events → `ClearBlogContentCacheAction`/`ClearBlogTagCacheAction`. Declaring the dependency makes the cache contract discoverable and lets the platform reason about it. — `src/Providers/BlogServiceProvider.php`, `capell.json` — M
- **Harden the `BuildArticleMetaDataAction` throw path** — it throws a bare `Exception` ("Tag results page not found…") when an article has tags but no resolvable tag-results page. On a public article render this would surface as a 500 for a content-config gap rather than degrading gracefully. Consider logging + omitting tag links instead of throwing on the render path. — `src/Actions/BuildArticleMetaDataAction.php` — M

## 3. Missing Features (gaps)

Note: `capell.json` `capabilities: []` is empty, so there is no declared capability surface to tie gaps to — declaring capabilities is itself gap zero. Against blog norms:

- **RSS / Atom feeds** — no feed generation anywhere (`rss`/`atom`/`feed` greps return nothing in `src`/`resources`). Table-stakes for a blog; strong free-tier differentiator and an SEO/syndication win. (table-stakes)
- **Reading time** — not computed or rendered (`reading_time`/`readingTime` absent). Cheap to add as a derived `ArticleMetaData` field. (table-stakes)
- **Comments** — none, and no documented integration seam. Best shipped as a cross-sell hook to a future `comments` package rather than in-package. (differentiator via separate package)
- **Structured data / JSON-LD (`Article`/`BlogPosting`)** — `BuildArticleMetaDataAction` builds tags/author/tag-links only; no schema.org output. Natural hand-off to `seo-suite` (listed under "Best Used With") but Blog should expose the author/published/modified data needed. (differentiator)
- **Featured / pinned articles** — no featured flag on `Article` (fillable: layout_id, meta, name, order, uuid, visible_from/until, site_id, blueprint_id; `order` exists but no featured concept). (table-stakes)
- **Categories** — taxonomy is tags-only (via `capell-app/tags`); no hierarchical category page type alongside `Tag`. (differentiator)
- **Author archive pages** — author metadata renders inline (`page/author.blade.php`) but there is no per-author listing page/route, unlike the existing `Tag` page type. (table-stakes)
- **Scheduling beyond visible_from/until** — `Article` has `visible_from`/`visible_until` (publish window) but no editorial scheduled-publish/queue UX; `publishing-studio` is a dependency, so the calendar seam exists to build on. (differentiator)
- **Related posts** — present (`Related` widget, tag-overlap via `TagLoader::getPageTags`); keep as a strength, not a gap.
- **Pagination** — present (`x-capell::pagination` with wire-links in `results-slot.blade.php`); strength.

## 4. Issues / Risks

- **Stub health checks misrepresent reliability** — `src/Health/BlogHealthCheck.php` has no check logic; 2 of its 4 declared checks are `critical`. A health dashboard would report nothing actionable while implying critical coverage. (highest-priority risk) — `src/Health/BlogHealthCheck.php`, `capell.json`
- **CHANGELOG is effectively empty** — only an `Unreleased` line ("Prepared package metadata and documentation…"); no version history for a 4.x-dev package. — `CHANGELOG.md`
- **`composer.json` description is a placeholder** — `"Blog for Capell"` versus the rich `capell.json` description; weak for Packagist/marketplace listing. — `composer.json`
- **Test coverage gaps**: tests exist for pages (`ArticlePageTest`, `ArchivesPageTest`, `TagPageTest`, `TagsPageTest`, `ArticlesPageTest`), resource CRUD, widgets, media, and static-site export — solid breadth. But: (a) **no test asserts anonymous/non-admin output omits admin internals** (no `assertDontSee` for editor markers, model IDs, signed edit URLs, package names); page tests use `assertSeeText`/`assertDontSeeText` on titles only. (b) **No test exercises `BlogHealthCheck`** (nothing to exercise). (c) The Capell skill requires "rendering/cache changes need tests proving anonymous and non-admin safety" — that explicit safety assertion is absent. — `tests/Feature/Pages/*`
- **Cache-version increment is not cluster-reviewed here but looks safe** — `ClearBlogContentCacheAction::incrementCacheVersion` uses `Cache::store()->add()/increment()` with a `forever` fallback (single-key ops, no `scan`/`keys`). `ClearBlogTagCacheAction` runs a raw `DB::table('taggables')->join('pages')` to find affected sites — verify the `pages` join is correct given articles live in `articles` (morph via `taggable_type`), not `pages`; a wrong join would silently under-invalidate tag caches. — `src/Actions/ClearBlogTagCacheAction.php`
- **Performance budgets are tight and unverified** — manifest sets `frontendRenderBudgetMs: 20` and `adminQueryBudget: 40`. The `Blog` Livewire page issues at least 3 loader passes in `setup()` (paginated results, 4 latest articles, sidebar tags + tag-results page) each with `with(['tags'])`; no test asserts the query budget. Add a query-count assertion to protect the 40-query admin budget and the 20ms render budget. — `src/Livewire/Page/Blog.php`, `capell.json`
- **i18n**: user-facing strings are translated (`__('capell-blog::generic.read_article')`, `capell-frontend::generic.visible_from`) and author output is escaped (`nl2br(e($author->bio))`) — good. Only `en` lang files ship; no risk, just no bundled locales. — `resources/lang/en/*`
- **Public render safety: PASS (with the test gap above)** — Blade components receive hydrated Data (`BlogResultsViewData`, `ArticleMetaData`); loaders centralise queries and call `RenderedModelTracker`; frontend listing uses `PageLoader::getPages` (core applies publish filtering) so unpublished/scheduled articles are not leaked through Blog's own code. Risk is unproven-by-test, not observed-in-code.

## 5. Marketplace & Positioning

Blog is a foundation, bundled, free package — it is a primary reason to choose Capell for content/marketing sites, and the anchor for cross-sell into SEO and engagement add-ons. Its 11-package dependency footprint means it effectively *is* a large slice of the foundation bundle; position it as the editorial centrepiece, not a bolt-on.

**Current `marketplace.summary`**: *"Blog adds article publishing, archive pages, tag pages, article widgets, Site Discovery sitemap contributions, and frontend Livewire page components to Capell."* — accurate but inward-facing (lists mechanisms: "Livewire page components", "Site Discovery sitemap contributions") rather than outcomes. Reads like a commit message.

**Improved summary**: *"Publish articles, archives, tag pages, and related-article widgets with multilingual, multi-site, SEO-ready output — the editorial layer built into Capell's foundation."*

**Current composer `description`**: `"Blog for Capell"` — placeholder.

**Improved composer `description`**: *"Editorial publishing for Capell: articles, archive and tag pages, related/article widgets, sitemap and SEO support, multilingual and multi-site."*

**Free/bundle vs premium**: keep the core (articles, archives, tags, related, sitemap) free/foundation — it is table-stakes and drives adoption. Premium/cross-sell candidates: RSS/Atom + JSON-LD as part of **seo-suite**; comments via a dedicated **comments** package; author-archive + editorial-scheduling as a **publishing-studio** upsell; newsletter digest of latest articles via a **newsletter** package.

**Screenshot/media gaps**: only 1 of 13 committed images is declared in the manifest; frontend captures are flagged blank in `docs/overview.md`. Regenerate frontend screenshots against a seeded demo and promote the admin index + create/edit + health-widget captures into `marketplace.screenshots[]`.

**Platform-pitch contribution**: Blog demonstrates the "structured publishing foundation" story — the same page/layout/translation/tag primitives power both pages and articles. **Cross-sell**: seo-suite (structured data, feeds), comments (engagement), tags (already a dep — surface it), newsletter (distribution).

**Keywords/tags (8–12)**: blog, articles, publishing, editorial, cms, tags, archive, multilingual, multi-site, sitemap, livewire, filament.

## 6. Prioritized Roadmap

| Item | Bucket | Effort | Impact | Section ref |
| --- | --- | --- | --- | --- |
| Implement the 4 declared health checks (2 critical) | Now | M | High | §2, §4 |
| Add anonymous/non-admin public-output safety tests | Now | M | High | §4 |
| Populate manifest `capabilities`/`settings`/`permissions` | Now | S | High | §2 |
| Reconcile screenshots into manifest + regenerate blank frontend captures | Now | S | Med | §1, §5 |
| Rewrite composer `description` + marketplace `summary` | Now | S | Med | §5 |
| Fix `ArticleTypeFactory` orphan/misnaming | Now | S | Low | §2 |
| Fix `ListArtcles` typo (test + widget) | Now | S | Low | §2 |
| Verify/fix `ClearBlogTagCacheAction` taggables→pages join | Now | S | Med | §4 |
| Add reading-time to `ArticleMetaData` | Next | S | Med | §3 |
| Add RSS/Atom feed generation | Next | M | High | §3 |
| Expose author/published/modified data for JSON-LD (seo-suite seam) | Next | M | High | §3, §5 |
| Add featured/pinned article flag + widget option | Next | M | Med | §3 |
| Rename `InstallPackageAction` + document install-vs-setup boundary | Next | S | Low | §2 |
| Add query-budget assertion for Blog page render (20ms/40-query) | Next | S | Med | §4 |
| Author archive page type + route | Later | M | Med | §3 |
| Hierarchical categories alongside tags | Later | L | Med | §3 |
| Comments integration seam (separate package) | Later | L | Med | §3, §5 |
| Editorial scheduling UX via publishing-studio | Later | M | Med | §3, §5 |
