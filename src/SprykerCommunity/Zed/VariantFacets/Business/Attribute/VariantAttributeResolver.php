<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets\Business\Attribute;

use Generated\Shared\Transfer\ProductSearchAttributeCriteriaTransfer;
use SprykerCommunity\Shared\VariantFacets\VariantFacetsConfig;
use SprykerCommunity\Zed\VariantFacets\Dependency\Facade\VariantFacetsToProductFacadeInterface;
use SprykerCommunity\Zed\VariantFacets\Dependency\Facade\VariantFacetsToProductSearchFacadeInterface;

class VariantAttributeResolver implements VariantAttributeResolverInterface
{
    /**
     * @var string
     */
    protected const FILTER_TYPE_SINGLE_SELECT = 'single-select';

    /**
     * @var string
     */
    protected const FILTER_TYPE_MULTI_SELECT = 'multi-select';

    /**
     * @var string
     */
    protected const FILTER_TYPE_RANGE = 'range';

    /**
     * @var \SprykerCommunity\Zed\VariantFacets\Dependency\Facade\VariantFacetsToProductSearchFacadeInterface
     */
    protected $productSearchFacade;

    /**
     * @var \SprykerCommunity\Zed\VariantFacets\Dependency\Facade\VariantFacetsToProductFacadeInterface
     */
    protected $productFacade;

    /**
     * Cached for the lifetime of this instance (one per publish batch) — the configured facet
     * attribute list doesn't change mid-publish, and this otherwise runs once per abstract.
     *
     * @var array<string, string>|null
     */
    protected $facetAttributeTypesByKey;

    public function __construct(
        VariantFacetsToProductSearchFacadeInterface $productSearchFacade,
        VariantFacetsToProductFacadeInterface $productFacade,
    ) {
        $this->productSearchFacade = $productSearchFacade;
        $this->productFacade = $productFacade;
    }

    /**
     * @param array<int, array<string, mixed>> $spyProducts
     * @param int $idLocale
     *
     * @return array<int, array{sku: string, vals: array<string, string|array<string>>, nums: array<string, float>}>
     */
    public function resolveVariantAttributes(array $spyProducts, int $idLocale): array
    {
        $facetAttributeTypesByKey = $this->getFacetAttributeTypesByKey();

        if ($facetAttributeTypesByKey === []) {
            return [];
        }

        $variantFacetsProductVariantTransfers = [];

        foreach ($spyProducts as $spyProduct) {
            if (!$this->isSearchable($spyProduct, $idLocale)) {
                continue;
            }

            $variantFacetsProductVariantTransfer = $this->resolveConcreteVariantAttributes($spyProduct, $facetAttributeTypesByKey);

            if ($variantFacetsProductVariantTransfer === null) {
                continue;
            }

            $variantFacetsProductVariantTransfers[] = $variantFacetsProductVariantTransfer;
        }

        return $variantFacetsProductVariantTransfers;
    }

    /**
     * @param array<string, mixed> $spyProduct
     * @param array<string, string> $facetAttributeTypesByKey
     *
     * @return array{sku: string, vals: array<string, string|array<string>>, nums: array<string, float>}|null
     */
    protected function resolveConcreteVariantAttributes(array $spyProduct, array $facetAttributeTypesByKey): ?array
    {
        $attributes = $this->productFacade->decodeProductAttributes($spyProduct['attributes'] ?? '{}');

        $vals = [];
        $nums = [];

        foreach ($facetAttributeTypesByKey as $attributeKey => $facetType) {
            if (!array_key_exists($attributeKey, $attributes)) {
                continue;
            }

            $attributeValue = $attributes[$attributeKey];

            if ($facetType === static::FILTER_TYPE_RANGE) {
                // A `product_management_attribute.is_multiple` numeric attribute (multiple simultaneous
                // values on one concrete) has no sensible single min/max slider meaning here — skip
                // rather than silently corrupt via a failed float cast on an array.
                if (is_numeric($attributeValue)) {
                    $nums[$attributeKey] = (float)$attributeValue;
                }

                continue;
            }

            // `is_multiple` string attributes (e.g. a striped shoe that's both red AND green at once —
            // real, configured in this project's own product_management_attribute.csv for `farbe`) are
            // stored as a JSON array. Write the array through as-is rather than casting it to a single
            // string (PHP would silently produce the literal string "Array") — OpenSearch keyword fields
            // are natively multi-valued, so `term`/`terms` queries and `terms` aggregations already do
            // the right thing against an array value with zero query-side changes needed.
            $vals[$attributeKey] = is_array($attributeValue)
                ? array_map(fn (mixed $value): string => (string)$value, array_values($attributeValue))
                : (string)$attributeValue;
        }

        if ($vals === [] && $nums === []) {
            return null;
        }

        return [
            'sku' => $spyProduct['sku'],
            'vals' => $vals,
            'nums' => $nums,
        ];
    }

    /**
     * Mirrors `Spryker\Zed\ProductPageSearch\Business\DataMapper\AbstractProductSearchDataMapper::
     * isSearchable()` — a concrete only contributes to search (facets included) when it has an active
     * `is_searchable` flag for the locale being indexed.
     *
     * @param array<string, mixed> $spyProduct
     * @param int $idLocale
     */
    protected function isSearchable(array $spyProduct, int $idLocale): bool
    {
        if (!isset($spyProduct['SpyProductSearches'])) {
            return false;
        }

        foreach ($spyProduct['SpyProductSearches'] as $spyProductSearch) {
            if ($spyProductSearch['fk_locale'] === $idLocale && $spyProductSearch['is_searchable'] === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string> Attribute key => 'vals' (single-select/multi-select) or 'nums' (range).
     */
    protected function getFacetAttributeTypesByKey(): array
    {
        if ($this->facetAttributeTypesByKey !== null) {
            return $this->facetAttributeTypesByKey;
        }

        $productSearchAttributeCollectionTransfer = $this->productSearchFacade->getProductSearchAttributeCollection(
            new ProductSearchAttributeCriteriaTransfer(),
        );

        $facetAttributeTypesByKey = [];

        foreach ($productSearchAttributeCollectionTransfer->getProductSearchAttributes() as $productSearchAttributeTransfer) {
            $key = $productSearchAttributeTransfer->getKey();
            $filterType = $productSearchAttributeTransfer->getFilterType();

            if ($key === null || $filterType === null || !preg_match(VariantFacetsConfig::FACET_NAME_PATTERN, (string)$key)) {
                continue;
            }

            if ($filterType === static::FILTER_TYPE_SINGLE_SELECT || $filterType === static::FILTER_TYPE_MULTI_SELECT) {
                $facetAttributeTypesByKey[$key] = 'vals';
            } elseif ($filterType === static::FILTER_TYPE_RANGE) {
                $facetAttributeTypesByKey[$key] = static::FILTER_TYPE_RANGE;
            }
        }

        $this->facetAttributeTypesByKey = $facetAttributeTypesByKey;

        return $this->facetAttributeTypesByKey;
    }
}
