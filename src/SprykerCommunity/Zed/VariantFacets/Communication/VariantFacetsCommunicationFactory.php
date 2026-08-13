<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets\Communication;

use Spryker\Zed\Kernel\Communication\AbstractCommunicationFactory;

/**
 * Empty on purpose: the two Communication plugins (`VariantAttributesPageDataExpanderPlugin`,
 * `VariantFacetMapExpanderPlugin`) only ever need `getFacade()`, resolved by the base
 * `Spryker\Zed\Kernel\Communication\AbstractPlugin` machinery without going through this class. It
 * still needs to exist — `@method ... getFactory()` on both plugins references it, and
 * `FactoryResolver::resolve()` requires a matching `<Module>CommunicationFactory` class in this exact
 * location even when nothing in it is ever called.
 *
 * @method \SprykerCommunity\Zed\VariantFacets\VariantFacetsConfig getConfig()
 */
class VariantFacetsCommunicationFactory extends AbstractCommunicationFactory
{
}
