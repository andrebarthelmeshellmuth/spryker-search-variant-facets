<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets\Business\Attribute;

interface VariantAttributeResolverInterface
{
    /**
     * Specification:
     * - Resolves the per-concrete facet values that core's own abstract-level facet indexing discards
     *   by unioning every concrete's attribute values per key (see spryker-page-json-dynamic-templates-
     *   merge-gotcha's sibling finding: `ProductPageAttribute::getCombinedProductAttributes()` in
     *   spryker/product-page-search).
     * - Only considers concretes searchable for `$idLocale` (`SpyProductSearches[fk_locale].is_searchable`),
     *   mirroring `AbstractProductSearchDataMapper::isSearchable()`.
     * - Only considers attribute keys currently configured in `spy_product_search_attribute` with
     *   filter_type single-select/multi-select (→ `vals`, string) or range (→ `nums`, float) — an
     *   attribute never configured as a facet contributes nothing.
     * - A concrete with no configured-facet attributes at all is omitted from the result entirely
     *   (not returned with empty vals/nums).
     *
     * @param array<int, array<string, mixed>> $spyProducts Raw `SpyProductAbstract.SpyProducts[]` rows —
     *   each with at least `sku`, `attributes` (a JSON-encoded object string), and `SpyProductSearches`.
     * @param int $idLocale
     *
     * @return array<int, array{sku: string, vals: array<string, string|array<string>>, nums: array<string, float>}>
     */
    public function resolveVariantAttributes(array $spyProducts, int $idLocale): array;
}
