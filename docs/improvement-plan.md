# Blog - Improvement & Growth Plan

> Package: capell-app/blog · Kind: package · Tier: premium · Product group: Capell Publishing Pro · Bundle: publishing-pro · Status: Active

## 1. Snapshot

Blog is a premium publishing package for articles, archives, tag pages, article widgets, optional discovery/analytics bridges, and frontend Livewire page components. It owns article schema, admin resources, page/renderable registration, cache invalidation, publishing-surface setup, sitemap/static export bridges, screenshot coverage, and broad tests. Current implementation depth is strong, but the manifest repeats the same health check several times, archive/tag/article frontend behavior deserves explicit edge-case coverage, and docs should make cache and optional bridge behavior easier for package adopters to reason about.

## 2. Improvements (existing functionality)

1. **Remove duplicate health check manifest entries.** `capell.json` repeats `Capell\Blog\Health\BlogHealthCheck` four times. Keep one health entry and add a manifest test preventing duplicate classes. Evidence: `capell.json`, `tests/Unit/ManifestRequirementsTest.php`. - **S**

2. **Expand frontend edge-case coverage for archive/tag/blog pages.** The runtime contributor prepares many data branches. Add direct tests for empty archives, invalid archive months, missing tag slugs, empty tag results, and pagination clamping. Evidence: `src/Support/BlogFrontendRuntimeManifestContributor.php`, `tests/Feature/Pages/*PageTest.php`. - **M** - **Shipped**

3. **Harden media/author loading against N+1 regressions.** Article pages load creator, profile image, image translations, latest articles, tags, and related articles. Add query-budget coverage for article, blog, archive, and tag routes with seeded media/tags/authors. Evidence: `BlogFrontendRuntimeManifestContributor::prepareArticlePage()`, `BuildArticleMetaDataAction`, media tests. - **M**

4. **Document optional bridge behavior.** Blog integrates with Layout Builder, Navigation, Tags, HTML Cache, Content Sections, Publishing Studio, Comments, Site Discovery, and static export. README/overview should state what is required, optional, and degraded when an optional package is absent. Evidence: `composer.json`, `capell.json dependencies`, provider bridge registration methods. - **S**

## 3. Missing Features (gaps)

Capabilities declared include blog admin/frontend, articles, archives, tags, widgets, Livewire, cache invalidation, Publishing Studio bridge, and Comments bridge.

- **No RSS/Atom feed.** Publishing packages commonly need syndication feeds.
- **No canonical redirect strategy for changed article slugs.** URL Manager integration is not visible in this package.
- **Shipped 2026-06-16: editorial analytics adoption docs are explicit.** README and overview now explain how Insights and GA4 Reports can be used as optional growth bridges for article entrances, engaged sessions, archive/tag discovery, referrers, declining articles, and widget performance without making Blog depend on analytics packages.
- **No package-local completion review.** The package has broad test coverage but no plan reconciliation yet.

## 4. Issues / Risks

1. **Important issue: duplicate health entries can confuse Marketplace/install tooling.** Recommended fix: de-dupe manifest health checks. - **P2**

2. **Important risk: rich frontend data preparation can regress on unusual archive/tag inputs.** Recommended fix: edge-case tests around empty and invalid routes. - **P2**

3. **Important risk: article pages can drift into N+1 behavior.** Recommended fix: query-budget tests with media, author, tags, and related content. - **P2**

4. **Improvement: optional bridge behavior is under-explained for adopters.** Recommended fix: docs matrix for required/optional packages and degraded behavior. - **P3**

## 5. Marketplace & Positioning

Blog should be positioned as Capell's premium publishing layer for teams that need articles, archives, tags, widgets, static export, and growth bridges without building a separate blogging app. For developers, emphasize page/renderable registration, cache invalidation, Livewire compatibility, and optional bridge contracts.

**Current summary:** "Blog adds premium article publishing, archive pages, tag pages, article widgets, optional discovery and analytics bridges, and frontend Livewire page components to Capell."

**Improved summary:** "Premium article publishing for Capell, with admin editorial tools, archive and tag pages, article widgets, static export, cache invalidation, and optional growth bridges."

**Media status:** Existing admin/frontend screenshots are useful. Refresh media after route-edge and cache behavior changes only if visual output changes.

**Cross-sell:** Tags, Navigation, Layout Builder, HTML Cache, Publishing Studio, Comments, Site Discovery, Insights/GA4, SEO Suite.

## 6. Prioritized Roadmap

| Item                                                       | Bucket | Effort | Impact | Section ref |
| ---------------------------------------------------------- | ------ | ------ | ------ | ----------- |
| Remove duplicate health entries from `capell.json`         | Done   | S      | High   | §2.1, §4.1  |
| Add archive/tag/blog edge-case route tests                 | Done   | M      | High   | §2.2, §4.2  |
| Add query-budget coverage for media/author/tag rich routes | Done   | M      | High   | §2.3, §4.3  |
| Document required/optional bridge behavior                 | Done   | S      | Medium | §2.4, §4.4  |
| Add RSS/Atom feed support                                  | Next   | M      | Medium | §3, §5      |
| Add URL Manager redirect integration for slug changes      | Next   | M      | Medium | §3          |
| Add analytics adoption docs tying widgets to Insights/GA4  | Done   | S      | Medium | §3          |
| Add editorial workflow templates                           | Later  | M      | Medium | §5          |
| Complete full package plan reconciliation                  | Later  | M      | Medium | §3          |

## 7. Verification

Implementation slice 1 collapsed duplicate `BlogHealthCheck` manifest entries into one package-health entry and added manifest coverage preventing duplicate health classes. Verify with:

```bash
vendor/bin/pest packages/blog/tests --configuration=phpunit.xml
```

For frontend behavior changes, include:

```bash
vendor/bin/pest packages/blog/tests/Feature/Pages packages/blog/tests/Unit/ManifestRequirementsTest.php --configuration=phpunit.xml
```

Implementation slice 3 added route-level frontend edge coverage for empty archive months, missing tag slugs, empty tag results, and invalid Blog pagination requests. Verify with:

```bash
vendor/bin/pest packages/blog/tests/Feature/Pages/ArticlesPageTest.php packages/blog/tests/Feature/Pages/ArchivesPageTest.php packages/blog/tests/Feature/Pages/TagPageTest.php --configuration=phpunit.xml
```

## 8. Completion Checklist

- [x] Package plan created from current code, manifest, docs, screenshots, and tests.
- [x] Comprehensive local review pass completed for provider, health, frontend runtime contributor, docs, screenshots, and tests.
- [x] Capell audience pass completed for editors, site owners, and developers.
- [x] Approved implementation slice 1 shipped: health manifest de-duplication.
- [x] Approved implementation slice 2 shipped: admin-resource, configurator, model, page-type, and page-variation contribution gaps replaced with concrete manifest classes and metadata.
- [x] Approved implementation slice 3 shipped: frontend route edge coverage for archive, tag, and pagination branches.
- [x] Focused Blog verification passed.
- [x] Package tests passed.
- [x] Repo preflight passed for changed files.
