<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\AggregationExtractor;

use Generated\Shared\Transfer\FacetConfigTransfer;
use Generated\Shared\Transfer\RangeSearchResultTransfer;
use SprykerCommunity\Client\VariantFacets\Aggregation\VariantFacetAggregationBuilder;

class VariantRangeExtractor implements VariantRangeExtractorInterface
{
    public function __construct(protected VariantFacetAggregationBuilder $aggregationBuilder)
    {
    }

    public function extract(
        FacetConfigTransfer $facetConfigTransfer,
        bool $isSelected,
        array $aggregations,
        array $requestParameters,
    ): RangeSearchResultTransfer {
        $facetName = $facetConfigTransfer->getName() ?? '';
        [$min, $max] = $this->extractMinMax($facetName, $isSelected, $aggregations);

        $parameterName = $facetConfigTransfer->getParameterName();
        $activeMin = $requestParameters[$parameterName]['min'] ?? $min;
        $activeMax = $requestParameters[$parameterName]['max'] ?? $max;

        return (new RangeSearchResultTransfer())
            ->setName($facetName)
            ->setConfig(clone $facetConfigTransfer)
            ->setMin((int)$min)
            ->setMax((int)$max)
            ->setActiveMin((int)$activeMin)
            ->setActiveMax((int)$activeMax);
    }

    /**
     * @param string $facetName
     * @param bool $isSelected
     * @param array<string, mixed> $aggregations
     *
     * @return array{0: float, 1: float}
     */
    protected function extractMinMax(string $facetName, bool $isSelected, array $aggregations): array
    {
        $nestedResult = $this->getNestedResult($facetName, $isSelected, $aggregations);

        if ($nestedResult === null) {
            return [0.0, 0.0];
        }

        $statsResult = $this->getStatsResult($facetName, $nestedResult);

        if ($statsResult === null) {
            return [0.0, 0.0];
        }

        return [(float)($statsResult['min'] ?? 0), (float)($statsResult['max'] ?? 0)];
    }

    /**
     * @param string $facetName
     * @param bool $isSelected
     * @param array<string, mixed> $aggregations
     *
     * @return array<string, mixed>|null
     */
    protected function getNestedResult(string $facetName, bool $isSelected, array $aggregations): ?array
    {
        $nestedName = $this->aggregationBuilder->getNestedAggregationName($facetName);

        if (!$isSelected) {
            return $aggregations[$nestedName] ?? null;
        }

        $globalName = VariantFacetAggregationBuilder::AGGREGATION_GLOBAL_PREFIX . $facetName;
        $rootFilterName = $this->aggregationBuilder->getRootFilterAggregationName($facetName);

        return $aggregations[$globalName][$rootFilterName][$nestedName] ?? null;
    }

    /**
     * @param string $facetName
     * @param array<string, mixed> $nestedResult
     *
     * @return array<string, mixed>|null
     */
    protected function getStatsResult(string $facetName, array $nestedResult): ?array
    {
        $valuesName = $this->aggregationBuilder->getValuesAggregationName($facetName);

        if (isset($nestedResult[$valuesName])) {
            return $nestedResult[$valuesName];
        }

        $innerFilterName = $this->aggregationBuilder->getInnerFilterAggregationName($facetName);

        return $nestedResult[$innerFilterName][$valuesName] ?? null;
    }
}
