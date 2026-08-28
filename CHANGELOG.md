# Changelog

All notable changes to this package are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Each version below also has a [GitHub release](../../releases) with the fuller write-up.

## [Unreleased]

### Documented
- OpenSearch 3.5 compatibility: verified end-to-end on a demoshop upgraded from 1.3.4 —
  `check-installation` confirms the `variant-facet` mapping is present and correctly shaped on 3.5.
  New `docs/opensearch-3.x-migration.md`.

## [1.0.2] - 2026-08-27

### Fixed
- Corrected composer `require` declarations (full dependency audit).
- Bumped `spryker/code-sniffer` 0.17.35 → 0.17.36.
- Applied Rector `IfToNullCoalescingAssignRector` (unpinned dev-tooling drift).

## [1.0.1] - 2026-08-23

### Changed
- CI: bumped `actions/checkout` v4 → v7.

## [1.0.0] - 2026-08-21

### Added
- Initial release: fixes cross-facet AND correctness for product variants. A `variant-facet` nested
  index field, a combined-`nested` query, and matching aggregation shapes ensure a facet combination
  (`color=Red AND size=40`) only matches when one single concrete satisfies all of it — stock Spryker
  matches per-attribute independently and can return a product whose Red variant and size-40 variant
  are different concretes.
- Precise per-concrete facet counts, range facets, and an off-by-default `inner_hits`-based
  storefront tile-swap. All verified live against OpenSearch 1.3.
- Documents the mapping-deploy-order hazard (a product export reaching the index before `search:setup`
  adds the `variant-facet` mapping auto-maps it as a plain `object`, permanently breaking
  `search:setup`) and points at `spryker-community/search-index-alias` (suggested, not required) to
  recover from it without reindex downtime.

[Unreleased]: ../../compare/v1.0.2...HEAD
[1.0.2]: ../../releases/tag/v1.0.2
[1.0.1]: ../../releases/tag/v1.0.1
[1.0.0]: ../../releases/tag/v1.0.0
