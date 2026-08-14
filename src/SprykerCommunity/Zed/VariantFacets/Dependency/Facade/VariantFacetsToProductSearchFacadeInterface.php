<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets\Dependency\Facade;

use Generated\Shared\Transfer\ProductSearchAttributeCollectionTransfer;
use Generated\Shared\Transfer\ProductSearchAttributeCriteriaTransfer;

interface VariantFacetsToProductSearchFacadeInterface
{
    public function getProductSearchAttributeCollection(
        ProductSearchAttributeCriteriaTransfer $productSearchAttributeCriteriaTransfer,
    ): ProductSearchAttributeCollectionTransfer;
}
