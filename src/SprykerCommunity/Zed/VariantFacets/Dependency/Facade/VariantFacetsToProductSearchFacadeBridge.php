<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets\Dependency\Facade;

use Generated\Shared\Transfer\ProductSearchAttributeCollectionTransfer;
use Generated\Shared\Transfer\ProductSearchAttributeCriteriaTransfer;

class VariantFacetsToProductSearchFacadeBridge implements VariantFacetsToProductSearchFacadeInterface
{
    /**
     * @var \Spryker\Zed\ProductSearch\Business\ProductSearchFacadeInterface
     */
    protected $productSearchFacade;

    /**
     * @param \Spryker\Zed\ProductSearch\Business\ProductSearchFacadeInterface $productSearchFacade
     */
    public function __construct($productSearchFacade)
    {
        $this->productSearchFacade = $productSearchFacade;
    }

    public function getProductSearchAttributeCollection(
        ProductSearchAttributeCriteriaTransfer $productSearchAttributeCriteriaTransfer,
    ): ProductSearchAttributeCollectionTransfer {
        return $this->productSearchFacade->getProductSearchAttributeCollection($productSearchAttributeCriteriaTransfer);
    }
}
