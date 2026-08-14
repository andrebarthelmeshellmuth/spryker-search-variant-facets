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
 * Ported from Produktkonfigurator's `FacetResultFormatterPlugin::isFacetUseful()`/
 * `isSingleFacetUseful()`, simplified: the original's `count($values) > 2 &&
 * !hasFacetValuesSameHitsAsResult(...)` branch is subsumed by its own `facetReducesResultSet(...)`
 * branch — a facet value's docCount can never exceed `$resultTotalHits` (it's a subset of the current
 * result), so "not all values equal the total" and "at least one value reduces the set" are the same
 * condition; the `>2` branch never fires without the other also firing. Ported as the two conditions
 * actually do — active-value-always-kept, plus the single reduces-the-set check — not the redundant one.
 * Produktkonfigurator's grouped min/max range-facet-pair handling (`isMultiPartFacetUseful()`,
 * `getGroupedFacetData()`) isn't ported at all: this package's own range facets are single, ungrouped
 * `RangeSearchResultTransfer`s (see `VariantRangeExtractor`), so that machinery doesn't apply here.
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
