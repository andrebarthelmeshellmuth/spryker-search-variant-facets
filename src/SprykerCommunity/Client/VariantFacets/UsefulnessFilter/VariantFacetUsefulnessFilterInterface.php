<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\UsefulnessFilter;

use Generated\Shared\Transfer\FacetSearchResultTransfer;
use Generated\Shared\Transfer\RangeSearchResultTransfer;

interface VariantFacetUsefulnessFilterInterface
{
    /**
     * Specification:
     * - A bucketed (`vals`) facet is useful if it has a currently-active value (the user must always be
     *   able to deselect it), OR at least one of its values has a `docCount` strictly lower than
     *   `$resultTotalHits` — meaning selecting it would actually remove at least one product from the
     *   current result set. A facet where every value's count equals the total (every remaining product
     *   already has every value) cannot narrow anything.
     *
     * @param \Generated\Shared\Transfer\FacetSearchResultTransfer $facetSearchResultTransfer
     * @param int $resultTotalHits
     */
    public function isBucketedFacetUseful(FacetSearchResultTransfer $facetSearchResultTransfer, int $resultTotalHits): bool;

    /**
     * Specification:
     * - A range (`nums`) facet is useful if its slider has a real span (`max > min`) — a range collapsed
     *   to a single value has nothing to drag.
     *
     * @param \Generated\Shared\Transfer\RangeSearchResultTransfer $rangeSearchResultTransfer
     */
    public function isRangeFacetUseful(RangeSearchResultTransfer $rangeSearchResultTransfer): bool;
}
