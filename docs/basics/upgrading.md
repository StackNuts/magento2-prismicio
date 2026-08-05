---
title: Upgrading the Prismic module
description: What to do when upgrading, and what changes in behaviour
---

## Upgrading to 4.2

### 1. Update the package

```bash
composer require elgentos/module-prismicio:^4.2
bin/magento setup:upgrade
bin/magento setup:di:compile
```

Compiling is required, not optional: the constructors of `Model\Api`, `Renderer\Page` and
`Block\StaticBlock` changed.

### 2. Enable the document cache

```bash
bin/magento cache:enable prismicio_documents
```

**Do this on every environment.** Magento does not enable a new cache type automatically, and the
cache state lives in `app/etc/env.php`, which is usually not in version control. This is the single
step that decides how the shop behaves when your repository is unreachable, so it is worth
double checking on production.

With the cache type enabled, every document is cached for 24 hours per store, website and language.
The Prismic webhook clears it when you publish, so editors do not wait for it to expire.

Without it the module still works, every page simply asks the API again, and there is no fallback
while the repository is unavailable.

### 3. Check code that builds or extends the changed classes

`Model\Api` gained three constructor arguments since 4.1.6: a logger, the document cache manager and
`Model\Api\State`. Dependency injection resolves them, so injecting the model needs no changes.
Anything that constructs it by hand, or extends it and calls `parent::__construct()`, has to pass
them.

#### Subclasses of `Block\StaticBlock`

```bash
grep -rn "extends StaticBlock" app/code
```

`Block\StaticBlock` no longer takes a cache manager and a store manager, so the arguments after them
shifted. A subclass that passes them on has to drop them, otherwise the cache manager arrives where
the content type is expected and every page rendering that block fails:

```diff
     parent::__construct(
         $context,
         $documentResolver,
         $linkResolver,
         $api,
-        $cacheManager,
-        $storeManager,
         $contentType,
         $identifier,
         $data
     );
```

Layout XML passes those arguments by name and needs no changes.

## What changes in behaviour

**A page survives an unreachable repository.** Content comes from Prismic; while the repository is
down or locked, documents are served from the document cache for up to 24 hours; after that a page
forwards to noroute and blocks render empty. An unreachable repository no longer produces an error
page: the api/v2 call is covered by error handling, where previously only the document queries were.

**One unreachable repository no longer breaks the other stores.** The alternate links block collects
documents across all stores, and creating the api for another store's repository was not covered by
its try/catch. In a multi-repo setup a single failing repository therefore broke every page of every
store, including stores whose own repository was perfectly healthy. A store whose repository fails is
now skipped and the rest keep their links.

**A document that cannot be loaded is logged instead of taking the page down.** Reporting a missing
document went through code that resolved the block reference a second time and threw an exception of
its own while doing so, before deciding whether exceptions may be thrown at all. That happened in
every application mode, so in production one document that could not be loaded took the whole page
with it.

**Documents are cached in more places than before.** Caching moved into `Model\Api`, so linked
documents in slices are cached too. Those used to be fetched live on every request, and can now be
up to 24 hours old. If that matters for a particular integration, the lifetime is the `ttl` argument
of `Model\Document\CacheManager`.

**Previews are never cached.** While a Prismic preview cookie is present the cache is bypassed, so an
editor sees the preview instead of the published document.

**An outage costs one failed request instead of one per document.** A failure is remembered for a
minute, so a page with twenty Prismic blocks no longer makes twenty failing calls and writes twenty
warnings. The next request after that minute tries the API again, so recovery needs no intervention.

**Responses that are missing content are not cached.** A page rendered without its Prismic content is
kept out of the full page cache and gets `no-store` headers, so an empty page does not outlive the
outage.

## What you see in the log

During an outage, one `warning` per request naming the repository that failed, and a `debug` line per
block that could not be rendered. Previously an outage produced one warning per document lookup,
which on a page with many Prismic blocks buried everything else.

## Removed

- `Model\Document\CacheManager::invalidate()` — it was never called and its tags never matched the
  ones written, so it could not invalidate anything. Publishing clears the cache type through the
  webhook.
- `Model\CacheTypes::TAG_DOCUMENT_ITEM` — only used by the method above.

## Verifying the upgrade

With a healthy repository, request a page twice. The second request should render the same page
without any traffic to Prismic, which shows the document cache is enabled and filling.

To see the fallback itself, point the API endpoint of one store at something that answers `403` and
request the page again. It should still render completely, from cache. Then clear the cache and
request once more:

```bash
bin/magento cache:clean prismicio_documents
```

The page should forward to noroute, or render with empty blocks, and never return a `500`.

## Upgrading from 3.x

Everything above applies, plus:

- 4.x adds two tables for frontend routes, so `bin/magento setup:upgrade` is required.
- 4.x introduces the `prismicio_documents` cache type described in step 2.
- Since 4.2 an unreachable repository is treated as missing content instead of an exception. If your
  project relied on that exception surfacing, note that `Model\Api` now returns `null` and logs a
  warning.
