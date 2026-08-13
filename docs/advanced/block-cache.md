---
title: Block Cache
description: Opt-in caching for Prismic content, purged by document on publish instead of cleaning the whole Full Page Cache
---

# Block Cache

By default, the module's webhook invalidates content changes with a complete clean of the Full
Page Cache - every page on the site, on every single publish, regardless of what actually
changed. Turn Block Cache on and the same webhook instead purges only the pages and cached blocks
that actually rendered the document that changed, leaving the rest of the site's cache untouched.

It does this using Magento's own built-in caching (`Magento\Framework\View\Element\AbstractBlock`'s
`getCacheLifetime()` / `getCacheKeyInfo()` / `IdentityInterface` mechanism, the same one Magento
core uses for products and categories) rather than a bespoke cache type, and it's opt-in per
block: turning the feature on doesn't make every Prismic block cached, only the ones that
explicitly participate.

## Enabling it

**Stores > Configuration > Elgentos > Prismic.IO > Block Cache** - turn it on and set a lifetime
(seconds).

## Opting a block in

- **`Elgentos\PrismicIO\Block\TemplateCacheable`** - a drop-in replacement for
  `Elgentos\PrismicIO\Block\Template`.
- **`Elgentos\PrismicIO\Block\StaticBlock`** already participates - no layout change needed.

### Where to add it

Apply `TemplateCacheable` to the root block a `prismicio_by_type_*.xml` layout builds for the
whole document - the one that receives the full document (`type`/`uid` included) via its
`reference` argument - not to the slices nested inside it:

```xml
<block class="Elgentos\PrismicIO\Block\TemplateCacheable" template="Elgentos_PrismicIO::pages/landingpage/landingpage.phtml">
    <arguments>
        <argument name="reference" xsi:type="string">data</argument>
    </arguments>
    <block name="prismicio_landingpage.slices" class="Elgentos\PrismicIO\Block\Slices" template="body" />
</block>
```

## Cache keys

A cached block's key includes the current document's type, UID, and language (in addition to the
usual block name and store), so the same reused block name/class rendering different documents
gets separate cache entries rather than colliding.

## Purging by document

The module's existing webhook (`https://your-store.com/prismicio/webhook/cache`) picks up its
purge strategy from the same **Block Cache > Enabled** toggle - no separate webhook or URL:

- **Off** (default): a complete clean of the Full Page Cache on every event, same as before this
  feature existed.
- **On**: purges only the block cache and Full Page Cache entries tagged for the documents that
  actually changed, leaving everything else untouched.

### Don't mix cached and uncached Prismic content once this is on

Only `TemplateCacheable`/`StaticBlock` get tagged. A page whose Prismic content only goes through
the plain `Template` class is invisible to the targeted purge, and once the toggle is on, it also
loses the complete cache clean that used to invalidate it - so it would simply stop updating on
publish. Move the Prismic layouts you care about onto `TemplateCacheable`/`StaticBlock` before
turning this on, or leave it off until you're ready to; there's no partial middle ground, the
webhook's strategy is site-wide.
