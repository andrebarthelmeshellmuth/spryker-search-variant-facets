<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\AggregationExtractor;

use Generated\Shared\Transfer\FacetConfigTransfer;
use Generated\Shared\Transfer\FacetSearchResultTransfer;

interface VariantFacetExtractorInterface
{
    /**
     * Specification:
     * - Reads back the raw Elasticsearch aggregation result built by
     *   `VariantFacetAggregationBuilder::buildUnselectedFacetAggregation()`/`buildSelectedFacetAggregation()`
     *   into a `FacetSearchResultTransfer`, matching EXACTLY the shape core's own `FacetExtractor`
     *   returns — required because the storefront's `FacetFilter::getFilteredFacets()` silently drops
     *   any facet entry that isn't `instanceof FacetSearchResultTransfer`/`RangeSearchResultTransfer`.
     * - Drops buckets with `doc_count === 0` (a value that can't narrow anything further, given the
     *   currently-selected facets).
     *
     * @param \Generated\Shared\Transfer\FacetConfigTransfer $facetConfigTransfer
     * @param bool $isSelected Whether this facet is currently selected — determines whether the
     *   `buildSelectedFacetAggregation()` (global-wrapped) or `buildUnselectedFacetAggregation()` (direct)
     *   shape was used to build the aggregation being read.
     * @param array<string, mixed> $aggregations The full `Elastica\ResultSet::getAggregations()` result.
     * @param array<string, mixed> $requestParameters
     */
    public function extract(
        FacetConfigTransfer $facetConfigTransfer,
        bool $isSelected,
        array $aggregations,
        array $requestParameters,
    ): FacetSearchResultTransfer;
}
