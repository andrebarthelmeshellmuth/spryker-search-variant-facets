<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\Aggregation;

use Elastica\Aggregation\AbstractAggregation;
use Elastica\Aggregation\Filter;
use Elastica\Aggregation\GlobalAggregation;
use Elastica\Aggregation\Nested;
use Elastica\Aggregation\ReverseNested;
use Elastica\Aggregation\Stats;
use Elastica\Aggregation\Terms;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchAll;
use SprykerCommunity\Client\VariantFacets\Query\VariantFacetQueryBuilderInterface;

class VariantFacetAggregationBuilder implements VariantFacetAggregationBuilderInterface
{
    /**
     * @var string
     */
    public const PATH_VARIANT_FACET = 'variant-facet';

    /**
     * @var string
     */
    public const AGGREGATION_GLOBAL_PREFIX = 'variant-global-';

    public function __construct(
        protected VariantFacetQueryBuilderInterface $variantFacetQueryBuilder,
        protected int $facetValueAggregationSize,
    ) {
    }

    public function buildUnselectedFacetAggregation(string $facetName, array $otherSelectedVariantSelections): AbstractAggregation
    {
        $nested = new Nested($this->getNestedAggregationName($facetName), static::PATH_VARIANT_FACET);
        $nested->addAggregation($this->createFilteredTermsAggregation($facetName, $otherSelectedVariantSelections));

        return $nested;
    }

    public function buildSelectedFacetAggregation(
        string $facetName,
        BoolQuery $baseBoolQuery,
        array $otherNonVariantFacetFilters,
        array $otherSelectedVariantSelections,
    ): AbstractAggregation {
        $filterBoolQuery = clone $baseBoolQuery;

        foreach ($otherNonVariantFacetFilters as $otherFacetFilter) {
            $filterBoolQuery->addFilter($otherFacetFilter);
        }

        $otherVariantQuery = $this->variantFacetQueryBuilder->build($otherSelectedVariantSelections);

        if ($otherVariantQuery !== null) {
            $filterBoolQuery->addFilter($otherVariantQuery);
        }

        $nested = new Nested($this->getNestedAggregationName($facetName), static::PATH_VARIANT_FACET);
        $nested->addAggregation($this->createFilteredTermsAggregation($facetName, $otherSelectedVariantSelections));

        $filterAggregation = new Filter($this->getRootFilterAggregationName($facetName), $filterBoolQuery);
        $filterAggregation->addAggregation($nested);

        $globalAggregation = new GlobalAggregation(static::AGGREGATION_GLOBAL_PREFIX . $facetName);
        $globalAggregation->addAggregation($filterAggregation);

        return $globalAggregation;
    }

    public function buildUnselectedRangeAggregation(string $facetName, array $otherSelectedVariantSelections): AbstractAggregation
    {
        $nested = new Nested($this->getNestedAggregationName($facetName), static::PATH_VARIANT_FACET);
        $nested->addAggregation($this->createFilteredStatsAggregation($facetName, $otherSelectedVariantSelections));

        return $nested;
    }

    public function buildSelectedRangeAggregation(
        string $facetName,
        BoolQuery $baseBoolQuery,
        array $otherNonVariantFacetFilters,
        array $otherSelectedVariantSelections,
    ): AbstractAggregation {
        $filterBoolQuery = clone $baseBoolQuery;

        foreach ($otherNonVariantFacetFilters as $otherFacetFilter) {
            $filterBoolQuery->addFilter($otherFacetFilter);
        }

        $otherVariantQuery = $this->variantFacetQueryBuilder->build($otherSelectedVariantSelections);

        if ($otherVariantQuery !== null) {
            $filterBoolQuery->addFilter($otherVariantQuery);
        }

        $nested = new Nested($this->getNestedAggregationName($facetName), static::PATH_VARIANT_FACET);
        $nested->addAggregation($this->createFilteredStatsAggregation($facetName, $otherSelectedVariantSelections));

        $filterAggregation = new Filter($this->getRootFilterAggregationName($facetName), $filterBoolQuery);
        $filterAggregation->addAggregation($nested);

        $globalAggregation = new GlobalAggregation(static::AGGREGATION_GLOBAL_PREFIX . $facetName);
        $globalAggregation->addAggregation($filterAggregation);

        return $globalAggregation;
    }

    /**
     * @param string $facetName
     * @param array<string, array{scope: string, value: mixed}> $otherSelectedVariantSelections
     *
     * @return \Elastica\Aggregation\Stats|\Elastica\Aggregation\Filter
     */
    protected function createFilteredStatsAggregation(string $facetName, array $otherSelectedVariantSelections)
    {
        $statsAggregation = new Stats($this->getValuesAggregationName($facetName));
        $statsAggregation->setField(sprintf('%s.nums.%s', static::PATH_VARIANT_FACET, $facetName));

        if ($otherSelectedVariantSelections === []) {
            return $statsAggregation;
        }

        $filterQuery = $this->variantFacetQueryBuilder->buildInnerBoolQuery($otherSelectedVariantSelections) ?? new MatchAll();

        $filterAggregation = new Filter($this->getInnerFilterAggregationName($facetName), $filterQuery);
        $filterAggregation->addAggregation($statsAggregation);

        return $filterAggregation;
    }

    /**
     * @param string $facetName
     * @param array<string, array{scope: string, value: mixed}> $otherSelectedVariantSelections
     *
     * @return \Elastica\Aggregation\Terms|\Elastica\Aggregation\Filter
     */
    protected function createFilteredTermsAggregation(string $facetName, array $otherSelectedVariantSelections)
    {
        $termsAggregation = new Terms($this->getValuesAggregationName($facetName));
        $termsAggregation->setField(sprintf('%s.vals.%s.keyword', static::PATH_VARIANT_FACET, $facetName));
        $termsAggregation->setSize($this->facetValueAggregationSize);
        $termsAggregation->addAggregation(new ReverseNested($this->getRootAggregationName($facetName)));

        if ($otherSelectedVariantSelections === []) {
            return $termsAggregation;
        }

        $filterQuery = $this->variantFacetQueryBuilder->buildInnerBoolQuery($otherSelectedVariantSelections) ?? new MatchAll();

        $filterAggregation = new Filter($this->getInnerFilterAggregationName($facetName), $filterQuery);
        $filterAggregation->addAggregation($termsAggregation);

        return $filterAggregation;
    }

    public function getNestedAggregationName(string $facetName): string
    {
        return 'variant-nested-' . $facetName;
    }

    public function getInnerFilterAggregationName(string $facetName): string
    {
        return 'variant-inner-filter-' . $facetName;
    }

    public function getRootFilterAggregationName(string $facetName): string
    {
        return 'variant-root-filter-' . $facetName;
    }

    public function getValuesAggregationName(string $facetName): string
    {
        return 'variant-values-' . $facetName;
    }

    public function getRootAggregationName(string $facetName): string
    {
        return 'variant-root-' . $facetName;
    }
}
