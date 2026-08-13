<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\AggregationExtractor;

use ArrayObject;
use Generated\Shared\Transfer\FacetConfigTransfer;
use Generated\Shared\Transfer\FacetSearchResultTransfer;
use Generated\Shared\Transfer\FacetSearchResultValueTransfer;
use SprykerCommunity\Client\VariantFacets\Aggregation\VariantFacetAggregationBuilder;

class VariantFacetExtractor implements VariantFacetExtractorInterface
{
    public function __construct(protected VariantFacetAggregationBuilder $aggregationBuilder)
    {
    }

    /**
     * @param \Generated\Shared\Transfer\FacetConfigTransfer $facetConfigTransfer
     * @param bool $isSelected
     * @param array<string, mixed> $aggregations
     * @param array<string, mixed> $requestParameters
     */
    public function extract(
        FacetConfigTransfer $facetConfigTransfer,
        bool $isSelected,
        array $aggregations,
        array $requestParameters,
    ): FacetSearchResultTransfer {
        $facetName = $facetConfigTransfer->getName() ?? '';
        $values = $this->extractValues($facetName, $isSelected, $aggregations);

        $facetSearchResultTransfer = (new FacetSearchResultTransfer())
            ->setName($facetName)
            ->setValues($values)
            ->setConfig(clone $facetConfigTransfer);

        $parameterName = $facetConfigTransfer->getParameterName();

        if (isset($requestParameters[$parameterName])) {
            $facetSearchResultTransfer->setActiveValue($requestParameters[$parameterName]);
        }

        return $facetSearchResultTransfer;
    }

    /**
     * @param string $facetName
     * @param bool $isSelected
     * @param array<string, mixed> $aggregations
     *
     * @return \ArrayObject<int, \Generated\Shared\Transfer\FacetSearchResultValueTransfer>
     */
    protected function extractValues(string $facetName, bool $isSelected, array $aggregations): ArrayObject
    {
        $values = new ArrayObject();

        $nestedResult = $this->getNestedResult($facetName, $isSelected, $aggregations);

        if ($nestedResult === null) {
            return $values;
        }

        $termsResult = $this->getTermsResult($facetName, $nestedResult);

        if ($termsResult === null) {
            return $values;
        }

        $rootAggregationName = $this->aggregationBuilder->getRootAggregationName($facetName);

        foreach ($termsResult['buckets'] ?? [] as $bucket) {
            $rootDocCount = $bucket[$rootAggregationName]['doc_count'] ?? 0;

            if ($rootDocCount === 0) {
                continue;
            }

            $values->append(
                (new FacetSearchResultValueTransfer())
                    ->setValue((string)$bucket['key'])
                    ->setDocCount($rootDocCount),
            );
        }

        return $values;
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
    protected function getTermsResult(string $facetName, array $nestedResult): ?array
    {
        $valuesName = $this->aggregationBuilder->getValuesAggregationName($facetName);

        if (isset($nestedResult[$valuesName])) {
            return $nestedResult[$valuesName];
        }

        $innerFilterName = $this->aggregationBuilder->getInnerFilterAggregationName($facetName);

        return $nestedResult[$innerFilterName][$valuesName] ?? null;
    }
}
