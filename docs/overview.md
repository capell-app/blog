# Blog

<!-- prettier-ignore-start -->

## What it does

Blog adds site-scoped, multilingual Articles plus the public blog index, monthly archives, tag listings, related-article widgets, and RSS/Atom feeds. Article visibility is controlled by publish and unpublish dates rather than by a background publishing job.

## Install and create the publishing surface

Install Layout Builder before Blog. Blog also requires Content Sections, Tags, Frontend, and HTML Cache. `capell:blog-install` publishes/runs the package migrations and assets, but does not create the blog's layouts, widgets, page types, or public pages.

Run `capell:blog-setup` to create those publishing defaults and ensure a `/blog` page, archive pages, and tag pages for every existing site and site language. If Navigation is installed and its tables exist, setup also adds the Blog page to Main and Footer navigations. The command's `--user`, `--sites`, `--languages`, and `--url` options are accepted only for installer compatibility and are ignored; setup always reconciles all current sites and languages. Review navigation and generated page translations after running it, especially on a multi-site installation.

Run setup again after adding a site or language when its Blog publishing surface is missing. The operation is designed to reuse existing records, but it can add the missing pages, widgets, translations, URLs, and navigation items.

## Create and publish articles

Open **Articles** in the admin. Article permissions and assigned-site access control which records an administrator can see or change; global administrators can work across sites. Choose the Site when creating, then add translated title/content, layout, featured image, tags, URLs, and publish dates. Tags are shared through the Tags package and have their own permissions.

The public article URL is generated beneath that site's Blog page for each translation. An article needs a matching translation and an enabled Page URL for the requested site/language before it can appear in listings or feeds. Use the article list's site and language filters to find incomplete coverage.

The publish panel has three practical behaviours:

- **Publish now** makes the article visible immediately;
- a future Visible from time makes it Scheduled; and
- Unpublish/Visible until hides it once that time has passed.

These transitions are evaluated from the database dates on each public query. No queue worker, scheduler, or cron publishing command is required, but the application clock and timezone must be correct. A scheduled article does not appear early in the blog index, archive/tag results, or feed. An expired article stops matching public queries without deleting its content. Publishing Studio can add workspace review and editorial-calendar integration when installed; it is not required for date-based visibility.

Saving an article, its translation, tags, or featured media clears the package's affected article/listing caches. Existing full-page HTML and external caches still follow the host cache regeneration policy. Verify the public URL after urgent changes rather than assuming every edge cache refreshed immediately.

## Public pages, tags, and archives

The setup surface creates the Blog index, `/blog/archives`, an archive wildcard below it, `/blog/tags`, and a tag wildcard below it. Do not delete or repurpose those system pages while their routes are in use. Diagnostics checks the publishing surface, cache wiring, author/related rendering, sitemap, and static-export integration.

Adding or removing tags updates tag listings and related widgets. A tag change can affect several sites when the tag is installation-wide or attached to articles on those sites, so review the affected tag URLs. Article public content is passed through Capell's public HTML sanitizer before rendering; unsafe markup may be removed even when it remains in the editor's stored content.

Soft-deleting an article removes it from normal public queries and keeps it available to restore. Force-delete is the irreversible content-removal path. Blog has no age-based archive or deletion schedule.

## Feeds

Blog exposes `/blog/feed.xml` and `/blog/feed.rss` as RSS 2.0, and `/blog/feed.atom` as Atom. Each request resolves the current Site Domain and language and returns up to the 20 latest published articles that have an enabled URL and translation for that site/language. Future, expired, other-site, and untranslated articles are excluded.

Feed responses are publicly cacheable for five minutes. A recently published or corrected article can therefore remain absent or stale in a browser, proxy, or CDN until that cache lifetime expires. Feeds contain the article title, URL, publication date, and summary; they do not publish the complete article body.

## Operational boundaries

Blog stores editorial content, URLs, tag relationships, user stamps, featured media relationships, and activity-log entries in plaintext application storage. It does not register a Privacy Center exporter or eraser. If an article contains personal data, handle export, correction, retention, and erasure through the owning editorial/privacy process, including media and backups.

There are no Blog-specific notification or retry queues. A missing public page, translation, URL, layout, or Site Domain is a configuration/data issue rather than a failed publishing job; use the article screen and Diagnostics to repair the surface, then recheck the exact site/language URL.

---

For how to use Blog, see the [admin guide](admin-guide.md).
For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
