---
name: blog
description: Article publishing, archive/tag pages, LayoutBuilder article widgets, and blog sitemaps. Use when editing Capell Blog articles, archives, tag pages, widgets, or sitemaps.
---

# Capell Blog

Article publishing, archive/tag pages, LayoutBuilder article widgets, and blog sitemaps.

## Look

- `packages/blog/src`
- `packages/blog/docs`
- `packages/blog/README.md`

## Rules

- Blog depends on LayoutBuilder; do not move widget logic into Core.
- Keep article publishing actions separate from Filament pages.
- Preserve sitemap and frontend Livewire behaviour when changing slugs.
- Verify customisations in the consuming application's test suite.
