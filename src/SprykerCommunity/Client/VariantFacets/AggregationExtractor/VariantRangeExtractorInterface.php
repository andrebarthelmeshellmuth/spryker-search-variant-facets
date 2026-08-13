<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\AggregationExtractor;

use Generated\Shared\Transfer\FacetConfigTransfer;
use Generated\Shared\Transfer\RangeSearchResultTransfer;

interface VariantRangeExtractorInterface
{
    /**
     * Specification:
     * - Reads back the `stats` aggregation built by `VariantFacetAggregationBuilder::
     *   buildUnselectedRangeAggregation()`/`buildSelectedRangeAggregation()` into a
     *   `RangeSearchResultTransfer` (min/max/activeMin/activeMax), matching core's own `RangeExtractor`
     *   shape exactly — required for the same reason as `VariantFacetExtractor`: the storefront's
     *   `FacetFilter::getFilteredFacets()` only accepts `FacetSearchResultTransfer`/
     *   `RangeSearchResultTransfer` instances.
     *
     * @param \Generated\Shared\Transfer\FacetConfigTransfer $facetConfigTransfer
     * @param bool $isSelected
     * @param array<string, mixed> $aggregations
     * @param array<string, mixed> $requestParameters
     */
    public function extract(
        FacetConfigTransfer $facetConfigTransfer,
        bool $isSelected,
        array $aggregations,
        array $requestParameters,
    ): RangeSearchResultTransfer;
}
