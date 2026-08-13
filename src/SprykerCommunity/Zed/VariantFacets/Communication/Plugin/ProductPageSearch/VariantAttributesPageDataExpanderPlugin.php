<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets\Communication\Plugin\ProductPageSearch;

use Generated\Shared\Transfer\ProductPageSearchTransfer;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use Spryker\Zed\ProductPageSearchExtension\Dependency\Plugin\ProductPageDataExpanderPluginInterface;

/**
 * @method \SprykerCommunity\Zed\VariantFacets\Business\VariantFacetsFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\VariantFacets\Communication\VariantFacetsCommunicationFactory getFactory()
 * @method \SprykerCommunity\Zed\VariantFacets\VariantFacetsConfig getConfig()
 */
class VariantAttributesPageDataExpanderPlugin extends AbstractPlugin implements ProductPageDataExpanderPluginInterface
{
    /**
     * {@inheritDoc}
     * - Resolves, straight from the raw `SpyProductAbstract.SpyProducts[]` rows this plugin receives
     *   (before core's own `getCombinedProductAttributes()` unions every concrete's values per attribute
     *   key and destroys the per-concrete pairing), each searchable concrete's own facet-relevant
     *   attribute values, and sets them on the product page search transfer for the later map-expander
     *   stage to write into the `variant-facet` document field.
     *
     * @api
     *
     * @param array<string, mixed> $productData
     * @param \Generated\Shared\Transfer\ProductPageSearchTransfer $productAbstractPageSearchTransfer
     *
     * @return void
     */
    public function expandProductPageData(array $productData, ProductPageSearchTransfer $productAbstractPageSearchTransfer)
    {
        $spyProducts = $productData['SpyProductAbstract']['SpyProducts'] ?? [];
        $idLocale = $productData['Locale']['id_locale'] ?? null;

        if ($idLocale === null) {
            return;
        }

        $productAbstractPageSearchTransfer->setVariantAttributes(
            $this->getFacade()->resolveVariantAttributes($spyProducts, $idLocale),
        );
    }
}
