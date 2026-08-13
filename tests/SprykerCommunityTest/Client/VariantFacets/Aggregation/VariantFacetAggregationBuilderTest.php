<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\VariantFacets\Aggregation;

use Codeception\Test\Unit;
use Elastica\Query\BoolQuery;
use Elastica\Query\Term;
use SprykerCommunity\Client\VariantFacets\Aggregation\VariantFacetAggregationBuilder;
use SprykerCommunity\Client\VariantFacets\Query\VariantFacetQueryBuilder;
use SprykerCommunity\Client\VariantFacets\VariantFacetsConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group VariantFacets
 * @group Aggregation
 * @group VariantFacetAggregationBuilderTest
 * Add your own group annotations below this line
 */
class VariantFacetAggregationBuilderTest extends Unit
{
    public function testBuildUnselectedFacetAggregationWrapsATermsAggregationInNested(): void
    {
        // Arrange
        $builder = $this->createBuilder();

        // Act
        $aggregation = $builder->buildUnselectedFacetAggregation('packaging_unit', []);
        $array = $aggregation->toArray();

        // Assert
        $this->assertSame('variant-facet', $array['nested']['path']);
        $termsAggregation = $array['aggs']['variant-inner-filter-packaging_unit']['terms'] ?? $array['aggs']['variant-values-packaging_unit']['terms'] ?? null;
        $this->assertNotNull($termsAggregation, 'With no other selections, the terms aggregation sits directly under the nested wrapper, unfiltered.');
        $this->assertSame('variant-facet.vals.packaging_unit.keyword', $termsAggregation['field']);
    }

    public function testBuildUnselectedFacetAggregationFiltersByOtherSelectedFacets(): void
    {
        // Arrange
        $builder = $this->createBuilder();
        $otherSelections = ['limitrange' => ['scope' => 'vals', 'value' => '90°C']];

        // Act
        $array = $builder->buildUnselectedFacetAggregation('packaging_unit', $otherSelections)->toArray();

        // Assert
        $filterAggregation = $array['aggs']['variant-inner-filter-packaging_unit'] ?? null;
        $this->assertNotNull($filterAggregation, 'A non-empty other-selections list must wrap the terms aggregation in a Filter, scoped to the OTHER selected facets only.');
        $this->assertArrayHasKey('filter', $filterAggregation);
        $this->assertArrayHasKey('variant-values-packaging_unit', $filterAggregation['aggs']);
    }

    public function testBuildSelectedFacetAggregationWrapsInGlobalAndRootFilter(): void
    {
        // Arrange
        $builder = $this->createBuilder();
        $baseBoolQuery = new BoolQuery();

        // Act
        $array = $builder->buildSelectedFacetAggregation('limitrange', $baseBoolQuery, [], [])->toArray();

        // Assert
        $this->assertArrayHasKey('global', $array, 'A SELECTED facet must escape the outer query scope via a global aggregation — otherwise its own filter would hide its own alternative values.');
        $rootFilterAggregation = $array['aggs']['variant-root-filter-limitrange'] ?? null;
        $this->assertNotNull($rootFilterAggregation);
        $this->assertArrayHasKey('variant-nested-limitrange', $rootFilterAggregation['aggs']);
    }

    public function testBuildSelectedFacetAggregationAppliesOtherNonVariantFacetFilters(): void
    {
        // Arrange
        $builder = $this->createBuilder();
        $baseBoolQuery = new BoolQuery();
        $otherNonVariantFilter = new Term(['brand' => 'Spryker']);

        // Act
        $array = $builder->buildSelectedFacetAggregation('limitrange', $baseBoolQuery, [$otherNonVariantFilter], [])->toArray();

        // Assert
        $rootFilterQuery = $array['aggs']['variant-root-filter-limitrange']['filter'];
        $this->assertArrayHasKey('bool', $rootFilterQuery);
        $this->assertContains(['term' => ['brand' => 'Spryker']], $rootFilterQuery['bool']['filter'] ?? [], 'A non-variant facet filter (e.g. brand) must still narrow this facet\'s own alternative-value counts.');
    }

    public function testBuildSelectedFacetAggregationAppliesOtherSelectedVariantSelections(): void
    {
        // Arrange
        $builder = $this->createBuilder();
        $baseBoolQuery = new BoolQuery();
        $otherSelections = ['packaging_unit' => ['scope' => 'vals', 'value' => 'Box']];

        // Act
        $array = $builder->buildSelectedFacetAggregation('limitrange', $baseBoolQuery, [], $otherSelections)->toArray();

        // Assert
        $rootFilterQuery = $array['aggs']['variant-root-filter-limitrange']['filter'];
        $this->assertArrayHasKey('nested', $rootFilterQuery['bool']['filter'][0] ?? [], 'The other SELECTED variant facet must also narrow this facet\'s own alternative-value counts, via the shared cross-facet-AND query.');
    }

    public function testBuildUnselectedRangeAggregationWrapsAStatsAggregationInNested(): void
    {
        // Arrange
        $builder = $this->createBuilder();

        // Act
        $array = $builder->buildUnselectedRangeAggregation('poweroutput', [])->toArray();

        // Assert
        $statsAggregation = $array['aggs']['variant-values-poweroutput']['stats'] ?? null;
        $this->assertNotNull($statsAggregation);
        $this->assertSame('variant-facet.nums.poweroutput', $statsAggregation['field']);
    }

    public function testBuildUnselectedRangeAggregationFiltersByOtherSelectedFacets(): void
    {
        // Arrange
        $builder = $this->createBuilder();
        $otherSelections = ['limitrange' => ['scope' => 'vals', 'value' => '90°C']];

        // Act
        $array = $builder->buildUnselectedRangeAggregation('poweroutput', $otherSelections)->toArray();

        // Assert
        $filterAggregation = $array['aggs']['variant-inner-filter-poweroutput'] ?? null;
        $this->assertNotNull($filterAggregation, 'Same filtered shape as the string-facet case, just wrapping a stats aggregation instead of terms.');
        $this->assertArrayHasKey('variant-values-poweroutput', $filterAggregation['aggs']);
    }

    public function testBuildSelectedRangeAggregationWrapsInGlobalAndRootFilter(): void
    {
        // Arrange
        $builder = $this->createBuilder();
        $baseBoolQuery = new BoolQuery();

        // Act
        $array = $builder->buildSelectedRangeAggregation('poweroutput', $baseBoolQuery, [], [])->toArray();

        // Assert
        $this->assertArrayHasKey('global', $array);
        $rootFilterAggregation = $array['aggs']['variant-root-filter-poweroutput'] ?? null;
        $this->assertNotNull($rootFilterAggregation);
        $this->assertArrayHasKey('variant-nested-poweroutput', $rootFilterAggregation['aggs']);
    }

    public function testAggregationNameHelpersAreStableAndFacetScoped(): void
    {
        // Arrange
        $builder = $this->createBuilder();

        // Act & Assert
        $this->assertSame('variant-nested-limitrange', $builder->getNestedAggregationName('limitrange'));
        $this->assertSame('variant-inner-filter-limitrange', $builder->getInnerFilterAggregationName('limitrange'));
        $this->assertSame('variant-root-filter-limitrange', $builder->getRootFilterAggregationName('limitrange'));
        $this->assertSame('variant-values-limitrange', $builder->getValuesAggregationName('limitrange'));
        $this->assertSame('variant-root-limitrange', $builder->getRootAggregationName('limitrange'));
    }

    protected function createBuilder(): VariantFacetAggregationBuilder
    {
        return new VariantFacetAggregationBuilder(new VariantFacetQueryBuilder(new VariantFacetsConfig()), 10);
    }
}
