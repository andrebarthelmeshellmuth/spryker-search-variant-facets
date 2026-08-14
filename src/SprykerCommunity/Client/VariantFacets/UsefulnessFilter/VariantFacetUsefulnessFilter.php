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
 * Deliberately omits a `count($values) > 2 && !hasFacetValuesSameHitsAsResult(...)` condition
 * sometimes seen in similar implementations: it's subsumed by this class's own
 * `facetReducesResultSet(...)` branch — a facet value's docCount can never exceed `$resultTotalHits`
 * (it's a subset of the current result), so "not all values equal the total" and "at least one value
 * reduces the set" are the same condition; the `>2` branch never fires without the other also firing.
 * Implements the two conditions that actually do the work — active-value-always-kept, plus the single
 * reduces-the-set check — not the redundant three-way version. Grouped min/max range-facet-pair
 * handling is not implemented: this package's own range facets are single, ungrouped
 * `RangeSearchResultTransfer`s (see `VariantRangeExtractor`), so that machinery doesn't apply here.
 *
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
