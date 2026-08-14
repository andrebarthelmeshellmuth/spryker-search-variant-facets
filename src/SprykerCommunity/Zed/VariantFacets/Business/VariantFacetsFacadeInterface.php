<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets\Business;

interface VariantFacetsFacadeInterface
{
    /**
     * Specification:
     * - Resolves, per searchable concrete of one product abstract, the facet values that core's own
     *   abstract-level facet indexing discards by unioning every concrete's values per attribute key —
     *   see `VariantAttributeResolverInterface::resolveVariantAttributes()` for the full rule set.
     *
     * @api
     *
     * @param array<int, array<string, mixed>> $spyProducts Raw `SpyProductAbstract.SpyProducts[]` rows.
     * @param int $idLocale
     *
     * @return array<int, array{sku: string, vals: array<string, string|array<string>>, nums: array<string, float>}>
     */
    public function resolveVariantAttributes(array $spyProducts, int $idLocale): array;
}
