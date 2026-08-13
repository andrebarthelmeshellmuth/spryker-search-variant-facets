<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\Plugin\ResultFormatter;

use Elastica\ResultSet;
use Generated\Shared\Transfer\FacetConfigTransfer;
use Generated\Shared\Transfer\FacetSearchResultTransfer;
use Generated\Shared\Transfer\RangeSearchResultTransfer;
use Spryker\Client\Kernel\AbstractFactory;
use Spryker\Client\SearchElasticsearch\Plugin\ResultFormatter\FacetResultFormatterPlugin;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchFactory;
use SprykerCommunity\Client\VariantFacets\FacetScope\IndexMappingFacetScopeStrategy;
use SprykerCommunity\Client\VariantFacets\VariantFacetsFactory;

/**
 * Drop-in replacement for core's `FacetResultFormatterPlugin` — register it in the SAME position
 * instead. Overrides facet-value lookup ONLY for variant-scoped facets (both 'vals' and 'nums'); every
 * other facet falls through to core's own extraction, unchanged.
 *
 * `getFactory()` is overridden below to resolve `SearchElasticsearchFactory` (not this package's own
 * `VariantFacetsFactory`) — see that method's own docblock. No class-level `@method` tag here
 * deliberately: one would contradict the real override and mislead static analysis.
 */
class VariantAwareFacetResultFormatterPlugin extends FacetResultFormatterPlugin
{
    /**
     * @var \SprykerCommunity\Client\VariantFacets\VariantFacetsFactory|null
     */
    protected $variantFacetsFactory;

    /**
     * @param \Elastica\ResultSet $searchResult
     * @param array<string, mixed> $requestParameters
     *
     * @return array<string, mixed>
     */
    #[\Override]
    protected function formatSearchResult(ResultSet $searchResult, array $requestParameters): array
    {
        $facetData = [];
        $facetConfig = $this->getSearchElasticsearchFactory()->getSearchConfig()->getFacetConfig();
        $aggregations = $searchResult->getAggregations();
        $activeFilters = $facetConfig->getActiveParamNames($requestParameters);
        $resultTotalHits = $searchResult->getTotalHits();

        foreach ($facetConfig->getAll() as $facetName => $facetConfigTransfer) {
            $scope = $this->getVariantFacetsFactory()->createFacetScopeResolver()->resolveScope($facetName);
            $isSelected = in_array($facetName, $activeFilters, true);

            $facetResult = match ($scope) {
                IndexMappingFacetScopeStrategy::SCOPE_VALS => $this->formatValsFacet($facetConfigTransfer, $isSelected, $aggregations, $requestParameters, $resultTotalHits),
                IndexMappingFacetScopeStrategy::SCOPE_NUMS => $this->formatNumsFacet($facetConfigTransfer, $isSelected, $aggregations, $requestParameters),
                default => $this->formatCoreFacet($facetConfigTransfer, $aggregations, $requestParameters),
            };

            if ($facetResult === null) {
                continue;
            }

            $facetData[$facetName] = $facetResult;
        }

        return $facetData;
    }

    /**
     * @param \Generated\Shared\Transfer\FacetConfigTransfer $facetConfigTransfer
     * @param bool $isSelected
     * @param array<string, mixed> $aggregations
     * @param array<string, mixed> $requestParameters
     * @param int $resultTotalHits
     */
    protected function formatValsFacet(
        FacetConfigTransfer $facetConfigTransfer,
        bool $isSelected,
        array $aggregations,
        array $requestParameters,
        int $resultTotalHits,
    ): ?FacetSearchResultTransfer {
        $facetSearchResultTransfer = $this->getVariantFacetsFactory()
            ->createVariantFacetExtractor()
            ->extract($facetConfigTransfer, $isSelected, $aggregations, $requestParameters);

        if ($facetSearchResultTransfer->getValues()->count() === 0) {
            return null;
        }

        $isUselessFacetFilteringEnabled = $this->getVariantFacetsFactory()->getConfig()->isUselessFacetFilteringEnabled();

        if ($isUselessFacetFilteringEnabled && !$this->getVariantFacetsFactory()->createVariantFacetUsefulnessFilter()->isBucketedFacetUseful($facetSearchResultTransfer, $resultTotalHits)) {
            return null;
        }

        return $facetSearchResultTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\FacetConfigTransfer $facetConfigTransfer
     * @param bool $isSelected
     * @param array<string, mixed> $aggregations
     * @param array<string, mixed> $requestParameters
     */
    protected function formatNumsFacet(
        FacetConfigTransfer $facetConfigTransfer,
        bool $isSelected,
        array $aggregations,
        array $requestParameters,
    ): ?RangeSearchResultTransfer {
        $rangeSearchResultTransfer = $this->getVariantFacetsFactory()
            ->createVariantRangeExtractor()
            ->extract($facetConfigTransfer, $isSelected, $aggregations, $requestParameters);

        $isUselessFacetFilteringEnabled = $this->getVariantFacetsFactory()->getConfig()->isUselessFacetFilteringEnabled();

        if ($isUselessFacetFilteringEnabled && !$this->getVariantFacetsFactory()->createVariantFacetUsefulnessFilter()->isRangeFacetUseful($rangeSearchResultTransfer)) {
            return null;
        }

        return $rangeSearchResultTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\FacetConfigTransfer $facetConfigTransfer
     * @param array<string, mixed> $aggregations
     * @param array<string, mixed> $requestParameters
     */
    protected function formatCoreFacet(FacetConfigTransfer $facetConfigTransfer, array $aggregations, array $requestParameters): mixed
    {
        $extractor = $this
            ->getSearchElasticsearchFactory()
            ->createAggregationExtractorFactory()
            ->create($facetConfigTransfer);

        $aggregation = $this->getAggregationRawData($aggregations, $facetConfigTransfer);

        if ($aggregation === []) {
            return null;
        }

        return $extractor->extractDataFromAggregations($aggregation, $requestParameters);
    }

    /**
     * Resolves core's `SearchElasticsearchFactory` explicitly — see
     * `VariantAwareFacetQueryExpanderPlugin::getFactory()` for the full reasoning; the same namespace
     * mismatch applies here.
     * The return type stays the wider `AbstractFactory` (matching `$this->factory`'s own declared type)
     * — see `VariantAwareFacetQueryExpanderPlugin::getFactory()` for the full reasoning.
     */
    protected function getFactory(): AbstractFactory
    {
        if ($this->factory === null) {
            $this->factory = $this->getFactoryResolver()->resolve(FacetResultFormatterPlugin::class);
        }

        return $this->factory;
    }

    /**
     * Narrowly-typed accessor for this plugin's OWN code — see
     * `VariantAwareFacetQueryExpanderPlugin::getSearchElasticsearchFactory()` for the full reasoning.
     */
    protected function getSearchElasticsearchFactory(): SearchElasticsearchFactory
    {
        /** @var \Spryker\Client\SearchElasticsearch\SearchElasticsearchFactory */
        return $this->getFactory();
    }

    protected function getVariantFacetsFactory(): VariantFacetsFactory
    {
        if ($this->variantFacetsFactory === null) {
            /** @var \SprykerCommunity\Client\VariantFacets\VariantFacetsFactory $variantFacetsFactory */
            $variantFacetsFactory = $this->getFactoryResolver()->resolve(static::class);
            $this->variantFacetsFactory = $variantFacetsFactory;
        }

        return $this->variantFacetsFactory;
    }
}
