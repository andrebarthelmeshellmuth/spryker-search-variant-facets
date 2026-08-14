<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\Plugin\QueryExpander;

use Elastica\Query;
use Elastica\Query\BoolQuery;
use Generated\Shared\Transfer\FacetConfigTransfer;
use Spryker\Client\Kernel\AbstractFactory;
use Spryker\Client\SearchElasticsearch\Config\FacetConfigInterface;
use Spryker\Client\SearchElasticsearch\Plugin\QueryExpander\FacetQueryExpanderPlugin;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchFactory;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface;
use SprykerCommunity\Client\VariantFacets\FacetScope\IndexMappingFacetScopeStrategy;
use SprykerCommunity\Client\VariantFacets\VariantFacetsFactory;

/**
 * Drop-in replacement for core's `FacetQueryExpanderPlugin` — register it in the SAME position instead
 * of alongside it, so uninstalling this package is a one-line plugin swap with no republish needed.
 *
 * The FILTER-application step combines variant-scoped facet selections into ONE shared `nested` query (so
 * a single concrete must satisfy every selected variant-scoped facet at once), instead of core's default
 * of one independent `nested` query per facet (any concrete may satisfy each facet, possibly a different
 * one per facet — the bug this package exists to fix). The AGGREGATION step builds the
 * unselected/selected shapes (`VariantFacetAggregationBuilder`) for variant-scoped facets instead of
 * core's per-facet-independent counts, which `VariantAwareFacetResultFormatterPlugin` then reads back.
 *
 * `getFactory()` is overridden below to resolve `SearchElasticsearchFactory` (not this package's own
 * `VariantFacetsFactory`) — see that method's own docblock. No class-level `@method` tag here
 * deliberately: one would contradict the real override and mislead static analysis.
 */
class VariantAwareFacetQueryExpanderPlugin extends FacetQueryExpanderPlugin
{
    /**
     * @var \SprykerCommunity\Client\VariantFacets\VariantFacetsFactory|null
     */
    protected $variantFacetsFactory;

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface $searchQuery
     * @param array<string, mixed> $requestParameters
     */
    #[\Override]
    public function expandQuery(QueryInterface $searchQuery, array $requestParameters = []): QueryInterface
    {
        $facetConfig = $this->getSearchElasticsearchFactory()->getSearchConfig()->getFacetConfig();
        $query = $searchQuery->getSearchQuery();

        $facetFilters = $this->getFacetFilters($facetConfig, $requestParameters);

        $this->addFacetAggregationToQuery($query, $facetConfig, $facetFilters, $requestParameters);
        $this->addFacetFiltersToBoolQueryVariantAware($query, $facetConfig, $requestParameters);

        return $searchQuery;
    }

    /**
     * Replaces `addFacetFiltersToBoolQuery()`: applies non-variant-scoped facet filters exactly as core
     * does, and combines every SELECTED variant-scoped facet into one shared nested filter.
     *
     * @param \Elastica\Query $query
     * @param \Spryker\Client\SearchElasticsearch\Config\FacetConfigInterface $facetConfig
     * @param array<string, mixed> $requestParameters
     */
    protected function addFacetFiltersToBoolQueryVariantAware(Query $query, FacetConfigInterface $facetConfig, array $requestParameters): void
    {
        $boolQuery = $this->getBoolQuery($query);

        foreach ($facetConfig->getActive($requestParameters) as $facetConfigTransfer) {
            if ($this->getVariantFacetsFactory()->createFacetScopeResolver()->resolveScope($facetConfigTransfer->getName()) !== null) {
                continue;
            }

            $this->addNonVariantFacetFilter($boolQuery, $facetConfigTransfer, $requestParameters);
        }

        $variantSelections = $this->collectVariantSelections($facetConfig, $requestParameters);
        $variantFacetQuery = $this->getVariantFacetsFactory()->createVariantFacetQueryBuilder()->build($variantSelections);

        if ($variantFacetQuery === null) {
            return;
        }

        $boolQuery->addFilter($variantFacetQuery);
    }

    /**
     * Replaces `addFacetAggregationToQuery()`: non-variant-scoped facets get exactly core's own
     * per-facet-independent aggregation, unchanged; variant-scoped facets (both 'vals' terms-count and
     * 'nums' range-stats shapes) get the unselected/selected aggregations instead.
     *
     * @param \Elastica\Query $query
     * @param \Spryker\Client\SearchElasticsearch\Config\FacetConfigInterface $facetConfig
     * @param array<string, \Elastica\Query\AbstractQuery> $facetFilters
     * @param array<string, mixed> $requestParameters
     */
    #[\Override]
    protected function addFacetAggregationToQuery(Query $query, FacetConfigInterface $facetConfig, array $facetFilters, array $requestParameters): void
    {
        $boolQuery = $this->getBoolQuery($query);
        $activeFilters = $facetConfig->getActiveParamNames($requestParameters);
        $variantSelections = $this->collectVariantSelections($facetConfig, $requestParameters);

        foreach ($facetConfig->getAll() as $facetConfigTransfer) {
            // A facet config always has a name in practice; the empty-string fallback only satisfies
            // static analysis (FacetConfigTransfer::getName() is nullable in the generated transfer) —
            // an actually-nameless config resolves scope=null below and falls through to core's own
            // per-facet handling exactly as it would have without this cast.
            $facetName = $facetConfigTransfer->getName() ?? '';
            $scope = $this->getVariantFacetsFactory()->createFacetScopeResolver()->resolveScope($facetName);
            $isSelfSelected = in_array($facetName, $activeFilters, true);

            if ($scope === null) {
                $this->addNonVariantFacetAggregation($query, $facetConfigTransfer, $facetFilters, $boolQuery, $isSelfSelected);

                continue;
            }

            $otherSelections = $variantSelections;
            unset($otherSelections[$facetName]);

            $aggregationBuilder = $this->getVariantFacetsFactory()->createVariantFacetAggregationBuilder();
            $isRange = $scope === IndexMappingFacetScopeStrategy::SCOPE_NUMS;

            if ($isSelfSelected) {
                $otherNonVariantFilters = $this->getOtherNonVariantFacetFilters($facetConfig, $facetFilters, $requestParameters, $facetName);
                $aggregation = $isRange
                    ? $aggregationBuilder->buildSelectedRangeAggregation($facetName, $boolQuery, $otherNonVariantFilters, $otherSelections)
                    : $aggregationBuilder->buildSelectedFacetAggregation($facetName, $boolQuery, $otherNonVariantFilters, $otherSelections);
            } else {
                $aggregation = $isRange
                    ? $aggregationBuilder->buildUnselectedRangeAggregation($facetName, $otherSelections)
                    : $aggregationBuilder->buildUnselectedFacetAggregation($facetName, $otherSelections);
            }

            $query->addAggregation($aggregation);
        }
    }

    /**
     * @param \Elastica\Query $query
     * @param \Generated\Shared\Transfer\FacetConfigTransfer $facetConfigTransfer
     * @param array<string, \Elastica\Query\AbstractQuery> $facetFilters
     * @param \Elastica\Query\BoolQuery $boolQuery
     * @param bool $isSelfSelected
     */
    protected function addNonVariantFacetAggregation(
        Query $query,
        FacetConfigTransfer $facetConfigTransfer,
        array $facetFilters,
        BoolQuery $boolQuery,
        bool $isSelfSelected,
    ): void {
        $facetAggregation = $this
            ->getSearchElasticsearchFactory()
            ->createFacetAggregationFactory()
            ->create($facetConfigTransfer)
            ->createAggregation();

        $query->addAggregation($facetAggregation);

        if (!$isSelfSelected) {
            return;
        }

        $query->addAggregation($this->createGlobalAggregation($facetFilters, $facetConfigTransfer, $boolQuery, $facetAggregation));
    }

    /**
     * Non-variant facet filters for every OTHER currently-selected facet (mirrors core's own
     * `getGlobalAggregationFilters()` exclusion rule) — used to build a variant-scoped facet's own
     * "selected" aggregation base query. Variant-scoped OTHER facets are handled separately (precisely,
     * via a fresh nested query), not through this array.
     *
     * @param \Spryker\Client\SearchElasticsearch\Config\FacetConfigInterface $facetConfig
     * @param array<string, \Elastica\Query\AbstractQuery> $facetFilters
     * @param array<string, mixed> $requestParameters
     * @param string $excludeFacetName
     *
     * @return array<\Elastica\Query\AbstractQuery>
     */
    protected function getOtherNonVariantFacetFilters(
        FacetConfigInterface $facetConfig,
        array $facetFilters,
        array $requestParameters,
        string $excludeFacetName,
    ): array {
        $result = [];

        foreach ($facetConfig->getActive($requestParameters) as $facetConfigTransfer) {
            $name = $facetConfigTransfer->getName();

            if ($name === $excludeFacetName || !isset($facetFilters[$name])) {
                continue;
            }

            if ($this->getVariantFacetsFactory()->createFacetScopeResolver()->resolveScope($name) !== null) {
                continue;
            }

            $result[] = $facetFilters[$name];
        }

        return $result;
    }

    /**
     * @param \Spryker\Client\SearchElasticsearch\Config\FacetConfigInterface $facetConfig
     * @param array<string, mixed> $requestParameters
     *
     * @return array<string, array{scope: string, value: mixed}>
     */
    protected function collectVariantSelections(FacetConfigInterface $facetConfig, array $requestParameters): array
    {
        $selections = [];

        foreach ($facetConfig->getActive($requestParameters) as $facetConfigTransfer) {
            $scope = $this->getVariantFacetsFactory()->createFacetScopeResolver()->resolveScope($facetConfigTransfer->getName());

            if ($scope === null) {
                continue;
            }

            $value = $this->resolveTransformedFilterValue($facetConfigTransfer, $requestParameters);

            if ($value === null) {
                continue;
            }

            if ($scope === IndexMappingFacetScopeStrategy::SCOPE_NUMS) {
                $value = $this->normalizeRangeValue($value);

                if ($value['min'] === null && $value['max'] === null) {
                    continue;
                }
            }

            $selections[$facetConfigTransfer->getName()] = ['scope' => $scope, 'value' => $value];
        }

        return $selections;
    }

    /**
     * Mirrors core's `NestedRangeQuery::setMinMaxValues()`: accepts either `array{min, max}` (the normal
     * request-parameter shape, e.g. `?price[min]=10&price[max]=100`) or a `"min-max"` string.
     *
     * @param array<string, mixed>|string $value
     *
     * @return array{min: float|null, max: float|null}
     */
    protected function normalizeRangeValue($value): array
    {
        if (is_array($value)) {
            return [
                'min' => isset($value['min']) && $value['min'] !== '' ? (float)$value['min'] : null,
                'max' => isset($value['max']) && $value['max'] !== '' ? (float)$value['max'] : null,
            ];
        }

        $parts = explode('-', (string)$value, 2);

        return [
            'min' => $parts[0] !== '' ? (float)$parts[0] : null,
            'max' => ($parts[1] ?? '') !== '' ? (float)$parts[1] : null,
        ];
    }

    /**
     * @param \Elastica\Query\BoolQuery $boolQuery
     * @param \Generated\Shared\Transfer\FacetConfigTransfer $facetConfigTransfer
     * @param array<string, mixed> $requestParameters
     */
    protected function addNonVariantFacetFilter(BoolQuery $boolQuery, FacetConfigTransfer $facetConfigTransfer, array $requestParameters): void
    {
        $filterValue = $requestParameters[$facetConfigTransfer->getParameterName()] ?? null;
        $query = $this->createFacetQuery($facetConfigTransfer, $filterValue);

        if ($query === null) {
            return;
        }

        $boolQuery->addFilter($query);
    }

    /**
     * Same value resolution core's own `createFacetFilterQuery()` applies (empty-value skip + the
     * project's configured `FacetSearchResultValueTransformerPluginInterface`, if any) — kept identical
     * so a value-transformer configured for a variant-scoped facet still works.
     *
     * @param \Generated\Shared\Transfer\FacetConfigTransfer $facetConfigTransfer
     * @param array<string, mixed> $requestParameters
     *
     * @return mixed
     */
    protected function resolveTransformedFilterValue(FacetConfigTransfer $facetConfigTransfer, array $requestParameters)
    {
        $filterValue = $requestParameters[$facetConfigTransfer->getParameterName()] ?? null;

        if ($this->isFilterValueEmpty($filterValue)) {
            return null;
        }

        $valueTransformerPlugin = $this->getSearchElasticsearchFactory()
            ->createFacetValueTransformerFactory()
            ->createTransformer($facetConfigTransfer);

        if ($valueTransformerPlugin !== null) {
            return $valueTransformerPlugin->transformFromDisplay($filterValue);
        }

        return $filterValue;
    }

    /**
     * Resolves core's `SearchElasticsearchFactory` explicitly by class name — required because plugin
     * factory resolution is namespace-based, and this plugin's own namespace (`VariantFacets`) does not
     * match the module (`SearchElasticsearch`) every inherited parent method expects `getFactory()` to
     * return. See `FactoryResolver::resolve()`'s `object|string` signature — passing the parent class's
     * FQCN (not `$this`/`static::class`) is what makes this resolve to the RIGHT module's factory. The
     * return type stays the wider `AbstractFactory` (matching `$this->factory`'s own declared type) —
     * it's always a `SearchElasticsearchFactory` instance at runtime, but nothing here can prove that
     * statically without an unchecked cast.
     */
    protected function getFactory(): AbstractFactory
    {
        if ($this->factory === null) {
            $this->factory = $this->getFactoryResolver()->resolve(FacetQueryExpanderPlugin::class);
        }

        return $this->factory;
    }

    /**
     * Narrowly-typed accessor for this plugin's OWN code (as opposed to `getFactory()` above, which
     * exists only so inherited parent methods keep working and must stay `AbstractFactory`-typed to
     * match them) — every call site this package's own methods make against
     * `SearchElasticsearchFactory`-specific methods (`createFacetAggregationFactory()`,
     * `createFacetValueTransformerFactory()`, `getSearchConfig()`, ...) goes through this instead.
     */
    protected function getSearchElasticsearchFactory(): SearchElasticsearchFactory
    {
        /** @var \Spryker\Client\SearchElasticsearch\SearchElasticsearchFactory */
        return $this->getFactory();
    }

    /**
     * This plugin's OWN factory (`VariantFacets`), resolved separately from `getFactory()` above so the
     * two never collide in the inherited `$this->factory` cache slot.
     */
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
