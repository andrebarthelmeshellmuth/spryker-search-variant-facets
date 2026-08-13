<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets;

use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\Container;
use SprykerCommunity\Zed\VariantFacets\Dependency\Facade\VariantFacetsToProductFacadeBridge;
use SprykerCommunity\Zed\VariantFacets\Dependency\Facade\VariantFacetsToProductSearchFacadeBridge;

/**
 * @method \SprykerCommunity\Zed\VariantFacets\VariantFacetsConfig getConfig()
 */
class VariantFacetsDependencyProvider extends AbstractBundleDependencyProvider
{
    /**
     * @var string
     */
    public const FACADE_PRODUCT_SEARCH = 'FACADE_PRODUCT_SEARCH';

    /**
     * @var string
     */
    public const FACADE_PRODUCT = 'FACADE_PRODUCT';

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    #[\Override]
    public function provideBusinessLayerDependencies(Container $container): Container
    {
        $container = parent::provideBusinessLayerDependencies($container);
        $container = $this->addProductSearchFacade($container);
        $container = $this->addProductFacade($container);

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addProductSearchFacade(Container $container): Container
    {
        $container->set(static::FACADE_PRODUCT_SEARCH, fn (Container $container) => new VariantFacetsToProductSearchFacadeBridge($container->getLocator()->productSearch()->facade()));

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addProductFacade(Container $container): Container
    {
        $container->set(static::FACADE_PRODUCT, fn (Container $container) => new VariantFacetsToProductFacadeBridge($container->getLocator()->product()->facade()));

        return $container;
    }
}
