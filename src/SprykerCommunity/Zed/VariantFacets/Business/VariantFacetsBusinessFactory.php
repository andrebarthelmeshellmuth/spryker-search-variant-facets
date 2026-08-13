<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets\Business;

use Spryker\Zed\Kernel\Business\AbstractBusinessFactory;
use SprykerCommunity\Zed\VariantFacets\Business\Attribute\VariantAttributeResolver;
use SprykerCommunity\Zed\VariantFacets\Business\Attribute\VariantAttributeResolverInterface;
use SprykerCommunity\Zed\VariantFacets\Dependency\Facade\VariantFacetsToProductFacadeInterface;
use SprykerCommunity\Zed\VariantFacets\Dependency\Facade\VariantFacetsToProductSearchFacadeInterface;
use SprykerCommunity\Zed\VariantFacets\VariantFacetsDependencyProvider;

/**
 * @method \SprykerCommunity\Zed\VariantFacets\VariantFacetsConfig getConfig()
 */
class VariantFacetsBusinessFactory extends AbstractBusinessFactory
{
    public function createVariantAttributeResolver(): VariantAttributeResolverInterface
    {
        return new VariantAttributeResolver(
            $this->getProductSearchFacade(),
            $this->getProductFacade(),
        );
    }

    public function getProductSearchFacade(): VariantFacetsToProductSearchFacadeInterface
    {
        return $this->getProvidedDependency(VariantFacetsDependencyProvider::FACADE_PRODUCT_SEARCH);
    }

    public function getProductFacade(): VariantFacetsToProductFacadeInterface
    {
        return $this->getProvidedDependency(VariantFacetsDependencyProvider::FACADE_PRODUCT);
    }
}
