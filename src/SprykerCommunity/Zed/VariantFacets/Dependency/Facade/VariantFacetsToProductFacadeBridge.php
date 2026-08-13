<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets\Dependency\Facade;

class VariantFacetsToProductFacadeBridge implements VariantFacetsToProductFacadeInterface
{
    /**
     * @var \Spryker\Zed\Product\Business\ProductFacadeInterface
     */
    protected $productFacade;

    /**
     * @param \Spryker\Zed\Product\Business\ProductFacadeInterface $productFacade
     */
    public function __construct($productFacade)
    {
        $this->productFacade = $productFacade;
    }

    /**
     * @param string $attributes
     *
     * @return array<string, mixed>
     */
    public function decodeProductAttributes(string $attributes): array
    {
        return $this->productFacade->decodeProductAttributes($attributes);
    }
}
