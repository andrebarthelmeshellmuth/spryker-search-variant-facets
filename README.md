# Spryker Variant Facets

Cross-facet AND for product variants: fixes a long-standing Spryker core behaviour where selecting values
from *different* facets (e.g. `color=red`, `size=40`) matches an abstract product even when red and 40
come from two different concretes, not one that's actually red **and** 40 at once.

## Contents

- [What does this do?](#what-does-this-do)
- [Status](#status)
- [Root cause](#root-cause)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [How it works](#how-it-works)
- [Range facets](#range-facets)
- [Storefront tile-swap (optional)](#storefront-tile-swap-optional)
- [Limitations](#limitations)
- [Porting this fix into Spryker core](#porting-this-fix-into-spryker-core)
- [Testing and CI](#testing-and-ci)
- [License](#license)

## What does this do?

Take a product with variants across two attributes — say a safety limiter sold at two trip temperatures
(`90°C`, `130°C`) and three packaging options (`Item`, `5-pack`, `Box`) — where the catalog only actually
carries *some* of the 2×3 combinations, not all six. In stock Spryker, selecting `limitrange=90°C` AND
`packaging_unit=Box` in the storefront facet sidebar returns that product **even if no single concrete
combines those two values** — core's facet index only knows "this abstract has a 90°C concrete somewhere"
and "this abstract has a Box concrete somewhere" independently, not which concrete has which.

This package fixes that for any facet backed by `spy_product_search_attribute` whose values vary at the
**concrete** level (not the abstract level — a facet like `brand`, set once per abstract, is unaffected
and untouched by this package).

## Status

v1: cross-facet AND filtering, precise facet counts, and range facets are built and verified live against
a real OpenSearch 1.3 instance. `inner_hits`-based storefront tile-swap is built and verified live but
off by default. Not yet tagged/released.

## Root cause

`Spryker\Zed\ProductPageSearch\Business\Attribute\ProductPageAttribute::joinAttributeCollectionValues()`
takes every concrete's own attribute values and unions them **per attribute key**, independently, before
they ever reach the search document. A concrete's identity — which value of `limitrange` came from the
*same* concrete as which value of `packaging_unit` — is discarded at that point; nothing downstream can
recover it. No query-side fix is possible against core's own `string-facet`/`integer-facet` index, because
the information it would need was never indexed. See ["Porting this fix into Spryker
core"](#porting-this-fix-into-spryker-core) for how to read this as a defect report against core.

## Requirements

- PHP ≥ 8.3
- OpenSearch ≥ 1.3 (or an Elasticsearch version supporting `nested`, `reverse_nested`, `filter`, `global`,
  and `stats` aggregations, and `inner_hits` — all standard since ES 2.x / OS 1.0)
- `spryker/product-page-search` ≥ 3.0, `spryker/product-page-search-extension` ≥ 1.7 (real floor, verified
  via `composer check-floors` — see the [dependency floors](#testing-and-ci) note)
- A project that configures at least one variant-varying attribute as a facet in
  `spy_product_search_attribute` (`filter_type` = `single-select`, `multi-select`, or `range`)

## Installation

### 1. Install the package

```bash
composer require spryker-community/search-variant-facets
```

### 2. Generate transfers

```bash
console transfer:generate
```

This adds `PageIndexMap::VARIANT_FACET` and the `variantFacet`/`variantAttributes` properties on
`PageMapTransfer`/`ProductPageSearchTransfer`.

### 3. Regenerate the search index mapping

```bash
console search:setup
```

The package's `Shared/VariantFacets/Schema/page.json` fragment is picked up automatically by the same
`vendor/spryker-community/*/src/*/Shared/*/Schema/` glob every other community search package in this
family uses — no manual schema registration needed. This adds a `variant-facet` nested field to your page
index; it does **not** touch or remove core's existing `string-facet`/`integer-facet` fields, which stay
exactly as they are (see ["How it works"](#how-it-works) for why that matters for rollback).

### 4. Register the Zed plugins

In your project's `ProductPageSearchDependencyProvider`:

```php
protected function getDataExpanderPlugins(): array
{
    return [
        // ...your existing plugins...
        VariantFacetsConfig::PLUGIN_VARIANT_ATTRIBUTES_DATA => new VariantAttributesPageDataExpanderPlugin(),
    ];
}

protected function getProductAbstractMapExpanderPlugins(): array
{
    return [
        // ...your existing plugins...
        new VariantFacetMapExpanderPlugin(), // must be LAST
    ];
}
```

### 5. Replace the Client plugins

In your project's `CatalogDependencyProvider` (or wherever `FacetQueryExpanderPlugin`/
`FacetResultFormatterPlugin` are registered) — **replace**, don't add:

```php
protected function createCatalogSearchQueryExpanderPlugins(): array
{
    return [
        // ...
        // new FacetQueryExpanderPlugin(),           // REMOVE
        new VariantAwareFacetQueryExpanderPlugin(),   // ADD, same position
    ];
}

protected function createCatalogSearchResultFormatterPlugins(): array
{
    return [
        // ...
        // new FacetResultFormatterPlugin(),           // REMOVE
        new VariantAwareFacetResultFormatterPlugin(),   // ADD, same position
    ];
}
```

Both are drop-in replacements: they extend the core plugins and fall through to identical core behaviour
for every facet this package doesn't touch. **Rollback is exactly reverting this one step** — the
`variant-facet` field stays in the index unused, and everything works exactly as it did before.

### 6. Configure a facet and republish

Configure a variant-varying attribute as usual in `spy_product_search_attribute` (`filter_type`
`single-select`/`multi-select` for string values, `range` for numeric), then republish the affected
products:

```bash
console publish:trigger-events -r product_abstract -i <id>
console queue:worker:start --stop-when-empty
```

The package auto-detects which facets are variant-scoped by reading the live index mapping — no separate
enable step per facet.

## Configuration

Override `SprykerCommunity\Client\VariantFacets\VariantFacetsConfig` as `Pyz\Client\VariantFacets\VariantFacetsConfig`:

- `getForcedVariantScopedFacetNames(): array` — treat these facet names as variant-scoped even if the live
  mapping doesn't show them yet (e.g. before a first republish). Defaults to `[]`.
- `getForcedNonVariantScopedFacetNames(): array` — never treat these as variant-scoped, overriding the
  live mapping. Defaults to `[]`.
- `isMatchingVariantTileSwapEnabled(): bool` — see [Storefront tile-swap](#storefront-tile-swap-optional).
  Defaults to `false`.

## How it works

Each product's `variant-facet` field is a `nested` array with **one entry per searchable concrete**:

```json
"variant-facet": [
  { "sku": "STL-7010-1", "vals": { "limitrange": "90°C", "packaging_unit": "Item" }, "nums": {} },
  { "sku": "STL-7010-2", "vals": { "limitrange": "90°C", "packaging_unit": "5-pack" }, "nums": {} }
]
```

A query selecting `limitrange=90°C AND packaging_unit=Box` becomes **one** `nested` query with both
constraints inside the same `bool.filter`, instead of core's two independent `nested` queries — an
Elasticsearch `nested` query requires all its inner clauses to match the *same* array entry, which is
exactly the per-concrete grouping core's own index throws away. Facet counts use the same nested/filter
scaffolding with `reverse_nested` (bucket facets) or `stats` (range facets) to stay precise given whatever
else is currently selected — see the package's own tests for the exact aggregation shapes.

**Why `vals.<name>.keyword`, not `vals.<name>`**: `vals`/`nums` are plain `{"dynamic": true}` objects, not
`path_match` dynamic templates. This is deliberate: `IndexDefinitionMerger::merge()` (core) combines every
package's `page.json` schema fragment with `array_replace_recursive()`, which corrupts a shared
`dynamic_templates` array when two fragments both add entries to it (proven live — a naive
`dynamic_templates` fragment here throws `mapper_parsing_exception: A dynamic template must be defined
with a name` on the very first `search:setup`). Falling back to plain `dynamic: true` objects sidesteps
that entirely, at the cost of Elasticsearch's default string typing: unknown string sub-fields get a
`text` + `.keyword` multi-field, not a pure `keyword` — hence the suffix everywhere this package builds a
term/terms query. Numeric sub-fields (`nums.*`) don't need it; `VariantAttributeResolver` always casts to
`float` so the first-seen JSON shape types the field as `double`, not `long`.

Core's `string-facet`/`integer-facet` fields are **kept**, untouched, for every non-variant facet — this
package only replaces the two plugins that decide how to *use* the index, not the indexing of those
fields.

## Range facets

Range-type variant facets (`filter_type: range` in `spy_product_search_attribute`) are fully built and
verified end-to-end through a real CSV import, not just against a scratch index: `VariantAttributeResolver`
writes numeric values to `nums.*`, `VariantFacetQueryBuilder` builds `range` filter clauses,
`VariantFacetAggregationBuilder`/`VariantRangeExtractor` build/read `stats` aggregations for the min/max
range-slider bounds. Verified live via a real fixture (a product with a per-concrete `leadtime_days`
attribute): the range slider correctly reports the true min/max across concretes, and filtering by a
sub-range correctly includes/excludes products based on whether any of their concretes actually fall in
it. One import-order gotcha worth knowing if you hit the same `Undefined array key "<your-key>"` error:
a brand-new attribute key must be registered via the `product-attribute-key` import step (`Pyz\Zed\
DataImport\Business\Model\ProductAttributeKey\AddProductAttributeKeysStep`) before `product-search-
attribute` can reference it — not just added to `product_search_attribute.csv` directly.

## Storefront tile-swap (optional)

Off by default. When `isMatchingVariantTileSwapEnabled()` is `true`, the combined facet query requests
`inner_hits`, and `MatchingVariantResultFormatterPlugin` (registered additionally, alongside — not
replacing — your other result formatters) exposes `idProductAbstract => [matching concrete SKUs]` under
the `matchingVariantSkus` search-result key. There's no bundled widget: read it from your own product-tile
template exactly the way this same demoshop's `search-ranking` package's `randomImpact` payload is
consumed — a plain project-level Twig read, e.g.:

```twig
{% set matchingSkus = (_view.matchingVariantSkus | default([]))[product.id_product_abstract] | default([]) %}
{% if matchingSkus is not empty %}
    {# ProductStorageClient::findProductConcreteStorageDataByMappingForCurrentLocale('sku', matchingSkus[0]) #}
{% endif %}
```

## Facet usefulness filtering (optional)

Off by default. `Produktkonfigurator` (a real client project this author has worked on) hides a facet, or
a facet value, once it can no longer narrow the current result set — e.g. a value where every remaining
product already has it. Under core's OR-across-concretes counts that was only ever an approximation;
under this package's exact per-concrete counts it's now a precise statement, which is what makes the
feature worth offering here rather than leaving it as a Produktkonfigurator-specific opinion.

Enable via `Pyz\Client\VariantFacets\VariantFacetsConfig::isUselessFacetFilteringEnabled()`. The rule,
applied only to variant-scoped facets (everything else is untouched, exactly as with the rest of this
package):

- A bucketed facet (`vals`) is hidden unless it has a currently-active value (so you can always deselect
  your own choice) or at least one of its values would actually remove a product if selected.
- A range facet (`nums`) is hidden when its min and max have collapsed to the same value — nothing left
  to drag.

This is deliberately **off by default** and was a real open decision, not an oversight: it's a UX opinion
(hide noise vs. show every option regardless), not a correctness fix, and the right default plausibly
differs between a B2C storefront and a B2B/marketplace context where a professional buyer may want to see
every attribute value regardless of whether it currently narrows anything. Verified live: narrowing a
search down to a single matching concrete via one facet correctly collapses and hides an unrelated,
no-longer-discriminating facet entirely, while a facet with an active selection always stays visible.

Ported from Produktkonfigurator's `FacetResultFormatterPlugin::isFacetUseful()`/`isSingleFacetUseful()`,
simplified: the original's `count($values) > 2 && !allValuesEqualTheTotal` branch is subsumed by its own
"at least one value reduces the set" branch (a value's count can never exceed the total, so those two
conditions are logically the same once you drop the `>2` gate) — ported as the two conditions actually
do, not the redundant three-way OR. Produktkonfigurator's grouped min/max range-facet-pair machinery
wasn't ported — this package's range facets are single, ungrouped, so it doesn't apply.

## Multi-valued attributes

A `product_management_attribute.is_multiple` attribute (real, configured for `farbe` in this demoshop's
own data) can hold more than one value on a single concrete at once — e.g. a striped shoe that's red
**and** green simultaneously — decoded as a JSON array rather than a scalar. `VariantAttributeResolver`
writes that array through as-is to `vals.<key>`; no query-side handling is needed, since OpenSearch
keyword fields are natively multi-valued: a `term`/`terms` query matches if *any* stored value matches,
and a `terms` aggregation fans out into one bucket per value, each correctly counting the parent concrete
once per value it carries (verified live: a `["red","green"]` entry is matched by a `farbe=red` query and
counted in both the `red` and `green` aggregation buckets). Range (`nums`) facets don't support this — a
multi-valued numeric attribute has no well-defined single min/max meaning here, so such a value is
skipped rather than guessed at.

## Limitations

- Facet **aggregation counts** for non-variant facets (`brand`, `farbe`, ... whatever your project has)
  are completely untouched — this package changes nothing about them.
- Only `single-select`, `multi-select`, and `range` `filter_type`s are handled; anything else falls
  through to core unchanged.
- `total_fields.limit`/doc-size: the nested-per-concrete design adds roughly 100-150 bytes per concrete
  per configured variant facet key to each product document, and the field-count cost is a handful of
  dynamically-typed sub-fields per facet key rather than one static field per facet name — for catalogs
  with very large numbers of concretes per abstract or very many variant-scoped facet keys, measure before
  adopting at scale.

## Porting this fix into Spryker core

The root cause lives in `spryker/product-page-search`'s `ProductPageAttribute::
joinAttributeCollectionValues()`. A core-level fix would need to either (a) preserve per-concrete grouping
through to `ProductSearchAttributeMapper`/`AbstractFacetAggregation` and redesign the facet query/
aggregation builders to operate per-concrete rather than per-abstract, or (b) ship an index-shape change
similar to this package's `variant-facet` field as an opt-in core feature. This package intentionally
avoids forking any core class (see "How it works") specifically so it stays a pure additive install — a
core-level fix has more freedom (e.g. it wouldn't need the `.keyword` workaround, since a core PR could
extend `IndexDefinitionMerger` itself) but the underlying per-concrete nested-doc mechanics proved here
translate directly.

## Demo fixture (for testing against a real b2b-demo-marketplace checkout)

This package's own live verification (headline AND-filter case, precise facet counts, range facets) uses
a small, real fixture on top of the demoshop's stock catalog: `STL-7010` gets `limitrange`/
`packaging_unit` facets with two concretes deliberately excluded from search (an incomplete 2×3 variant
matrix, needed to prove the cross-facet-AND bug and its fix), and `HP-ECO-45K` gets a `leadtime_days`
range facet. None of this is committed to the demoshop itself — it's Spryker's official upstream, not a
fork this project owns (see "Testing and CI" below for the same reasoning applied to code).

```bash
php fixtures/apply.php /path/to/b2b-demo-marketplace
```

Idempotent (safe to re-run) and edits the target CSVs by header name, not line position, so it keeps
working even if the demoshop's own fixture data shifts around it. Then, from the demoshop root:

```bash
./docker/sdk console data:import product-attribute-key   # MUST run before product-search-attribute
./docker/sdk console data:import product-search-attribute
./docker/sdk console data:import product-concrete
./docker/sdk console publish:trigger-events -r product_abstract -i <STL-7010's and HP-ECO-45K's id_product_abstract>
./docker/sdk console queue:worker:start --stop-when-empty
```

If you're adding a NEW fixture claim (a different product, attribute key, or `product_search_attribute`
position) for this or another package in this toolkit, check/update `FIXTURE_CLAIMS.md` in the
`spryker-community/search-toolkit` repo first — `product_concrete.csv` only has two attribute slots per
concrete, and two packages' fixtures silently claiming the same one is a real, undetected-by-tooling
collision.

## Testing and CI

```bash
composer check-floors   # verifies declared dependency floors are real, not guessed
composer phpstan        # level 8, must be run from a host shop (needs Generated\Shared\Transfer\*)
composer rector-dry-run
vendor/bin/codecept run -c tests/SprykerCommunityTest/Client/VariantFacets/codeception.yml
vendor/bin/codecept run -c tests/SprykerCommunityTest/Zed/VariantFacets/codeception.yml
vendor/bin/codecept run -c tests/SprykerCommunityTest/Yves/VariantFacetsPresentation/codeception.yml
```

`phpstan`/`codecept` both need to run from inside a host Spryker shop (they use the shop's generated
Locator and `Generated\Shared\Transfer\*` classes) — `composer check-floors` is the one command that
works from a clean checkout of this package alone. The Presentation suite additionally needs a real
WebDriver browser session (`docker/sdk testing`, not plain `docker/sdk cli` — the latter doesn't inject
`SPRYKER_TEST_WEB_DRIVER_HOST`) and this package's own fixture applied (see "Demo fixture" above) — it drives the real storefront search page end to end and asserts the exact result counts
the fixture implies, closing the gap an earlier version of this README used to flag ("a full CSV-fixture
E2E wasn't possible") for both the cross-facet-AND case and range facets.

CI's `host-shop` job automates the "host Spryker shop" requirement above for `phpstan` and the Client/Zed
Codeception suites: it checks out the public `spryker-shop/b2b-demo-marketplace`, wires this package in as
a path repository (the same shape the Installation section above documents), runs
`console transfer:generate` + `console dev:ide-auto-completion:generate` (both pure codegen — no
DB/Redis/Elasticsearch needed), then runs phpstan and the two suites against it. The WebDriver
Presentation suite is deliberately NOT run in CI — it needs a live OpenSearch-backed catalog with this
package's own fixture applied plus a real browser session, which needs the full docker-compose stack this
repo's plain GitHub Actions runner doesn't have; it stays a local/manual gate.

## License

MIT. See [LICENSE](LICENSE).
