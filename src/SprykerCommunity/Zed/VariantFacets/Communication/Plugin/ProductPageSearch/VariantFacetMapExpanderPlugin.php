<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets\Communication\Plugin\ProductPageSearch;

use Generated\Shared\Transfer\LocaleTransfer;
use Generated\Shared\Transfer\PageMapTransfer;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use Spryker\Zed\ProductPageSearchExtension\Dependency\PageMapBuilderInterface;
use Spryker\Zed\ProductPageSearchExtension\Dependency\Plugin\ProductAbstractMapExpanderPluginInterface;
use SprykerCommunity\Shared\VariantFacets\VariantFacetsConfig as SharedVariantFacetsConfig;

/**
 * @method \SprykerCommunity\Zed\VariantFacets\Business\VariantFacetsFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\VariantFacets\Communication\VariantFacetsCommunicationFactory getFactory()
 * @method \SprykerCommunity\Zed\VariantFacets\VariantFacetsConfig getConfig()
 */
class VariantFacetMapExpanderPlugin extends AbstractPlugin implements ProductAbstractMapExpanderPluginInterface
{
    /**
     * {@inheritDoc}
     * - Writes the per-concrete facet values `VariantAttributesPageDataExpanderPlugin` resolved earlier
     *   into the page document's `variant-facet` nested field; abstracts with no variant-scoped facet
     *   data get no `variant-facet` field at all.
     * - Must be registered LAST in `getProductAbstractMapExpanderPlugins()`: this reads the array form of
     *   the product page search transfer, and every other expander in that stack only ever adds keys, so
     *   ordering is otherwise harmless — kept last anyway so a future expander that mutates
     *   `variant_attributes` itself is guaranteed to run first.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\PageMapTransfer $pageMapTransfer
     * @param \Spryker\Zed\ProductPageSearchExtension\Dependency\PageMapBuilderInterface $pageMapBuilder
     * @param array<string, mixed> $productData
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     *
     * @return \Generated\Shared\Transfer\PageMapTransfer
     */
    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter -- signature is fixed by ProductAbstractMapExpanderPluginInterface.
    public function expandProductMap(
        PageMapTransfer $pageMapTransfer,
        PageMapBuilderInterface $pageMapBuilder,
        array $productData,
        LocaleTransfer $localeTransfer,
    ) {
        // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter
        $variantAttributes = $productData[SharedVariantFacetsConfig::PAGE_INDEX_FIELD_VARIANT_ATTRIBUTES] ?? [];

        if ($variantAttributes !== []) {
            $pageMapTransfer->setVariantFacet($variantAttributes);
        }

        return $pageMapTransfer;
    }
}
