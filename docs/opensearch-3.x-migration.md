# Migrating to OpenSearch 3.x

Verified live end-to-end: a Spryker demoshop upgraded from **OpenSearch 1.3.4 to 3.5.0** (Lucene 10.3.2),
full re-export/reindex, `search-variant-facets:check-installation` re-run on 3.5 (it confirms the
`variant-facet` mapping is present and correctly shaped on 3.5), storefront facet queries re-run against
the live 3.5 cluster.

**This package needs no code change for OpenSearch 3.x.**

## Why it carries across unchanged

The variant-aware facet query this package builds and the `page.json` fragment it ships use only
constructs that behave identically on both engine lineages:

- **`nested` / `reverse_nested` aggregations**, `filter` / `global` sub-aggregations, and `stats` — all
  standard since ES 2.x / OS 1.0.
- **`inner_hits`** for the storefront tile swap — standard since ES 5.x.
- The **`variant-facet` object mapping** — a plain typed `object`. `check-installation` re-reads it off the
  live 3.5 index mapping and confirms it round-tripped.

A facet result set that was correct on 1.3.4 is byte-identical on 3.5 for the same catalog and query.

## One upgrade-time trap (not this package's code)

OpenSearch 3.x's bundled neural-search `SemanticMappingTransformer` runs on **every index create** and
rejects any mapping that declares `"some-field": { "type": "object", "properties": {} }` with
`class java.util.ArrayList cannot be cast to class java.util.Map` — PHP's `json_decode` turns the empty
`{}` into `[]`, which Spryker then PUTs. Confirm your merged `page.json` (yours, this package's, and any
other) carries no such empty block; Spryker Cloud Commerce removed them from five core packages
(SC-25160), and a project schema override that makes `properties` non-empty covers any that remain. See
`spryker-community/search-ranking`'s migration guide for the fuller write-up and the 1.3.x → 3.5 capability
delta (the genuine additions are the `hybrid` query and `_search/pipeline`, neither used here).
