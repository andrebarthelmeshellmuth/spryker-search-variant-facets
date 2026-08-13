<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets\Business;

use Spryker\Zed\Kernel\Business\AbstractFacade;

/**
 * @method \SprykerCommunity\Zed\VariantFacets\Business\VariantFacetsBusinessFactory getFactory()
 */
class VariantFacetsFacade extends AbstractFacade implements VariantFacetsFacadeInterface
{
    /**
     * @api
     *
     * @param array<int, array<string, mixed>> $spyProducts
     * @param int $idLocale
     *
     * @return array<int, array{sku: string, vals: array<string, string|array<string>>, nums: array<string, float>}>
     */
    public function resolveVariantAttributes(array $spyProducts, int $idLocale): array
    {
        return $this->getFactory()->createVariantAttributeResolver()->resolveVariantAttributes($spyProducts, $idLocale);
    }
}
