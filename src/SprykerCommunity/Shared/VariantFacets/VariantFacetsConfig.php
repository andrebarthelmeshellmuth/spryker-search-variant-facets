<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\VariantFacets;

class VariantFacetsConfig
{
    /**
     * Registration key of the variant-attributes data-expander plugin in the ProductPageSearch plugin
     * stack (`getDataExpanderPlugins()`).
     *
     * @var string
     */
    public const PLUGIN_VARIANT_ATTRIBUTES_DATA = 'PLUGIN_VARIANT_ATTRIBUTES_DATA';

    /**
     * Key `VariantAttributesPageDataExpanderPlugin` sets on the `ProductPageSearchTransfer` and
     * `VariantFacetMapExpanderPlugin` reads back off the array form of that same transfer
     * (`AbstractTransfer::toArray()` defaults to snake_case keys — this must match that convention,
     * not the transfer's camelCase property name).
     *
     * @var string
     */
    public const PAGE_INDEX_FIELD_VARIANT_ATTRIBUTES = 'variant_attributes';

    /**
     * A facet name becomes an Elasticsearch field path segment (`variant-facet.vals.<name>` /
     * `variant-facet.nums.<name>`), so it must be safe there — no dots, no `*`, nothing that could be
     * read as a path_match wildcard or collide with the `sku` sub-field. Product search attribute keys
     * are free-text in `spy_product_search_attribute`, so this is enforced defensively rather than
     * trusted from config.
     *
     * @var string
     */
    public const FACET_NAME_PATTERN = '/^[a-z0-9_]+$/i';
}
