<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\UsefulnessFilter;

use Generated\Shared\Transfer\FacetSearchResultTransfer;
use Generated\Shared\Transfer\RangeSearchResultTransfer;

/**
 * `isBucketedFacetUseful()` relies on every value's `docCount` being >= 1: `VariantFacetExtractor::
 * extractValues()` explicitly skips any aggregation bucket with a zero `doc_count` before a
 * `FacetSearchResultValueTransfer` is ever built for it, so a phantom zero-count value (which would
 * trivially satisfy `docCount < $resultTotalHits` and wrongly mark the facet "useful") can never reach
 * this class.
 */
class VariantFacetUsefulnessFilter implements VariantFacetUsefulnessFilterInterface
{
    public function isBucketedFacetUseful(FacetSearchResultTransfer $facetSearchResultTransfer, int $resultTotalHits): bool
    {
        if ($facetSearchResultTransfer->getActiveValue()) {
            return true;
        }

        foreach ($facetSearchResultTransfer->getValues() as $value) {
            if ($value->getDocCount() < $resultTotalHits) {
                return true;
            }
        }

        return false;
    }

    public function isRangeFacetUseful(RangeSearchResultTransfer $rangeSearchResultTransfer): bool
    {
        return ($rangeSearchResultTransfer->getMax() - $rangeSearchResultTransfer->getMin()) > 0;
    }
}
