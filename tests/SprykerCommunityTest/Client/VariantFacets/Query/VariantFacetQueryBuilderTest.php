<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\VariantFacets\Query;

use Codeception\Test\Unit;
use SprykerCommunity\Client\VariantFacets\Query\VariantFacetQueryBuilder;
use SprykerCommunity\Client\VariantFacets\VariantFacetsConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group VariantFacets
 * @group Query
 * @group VariantFacetQueryBuilderTest
 * Add your own group annotations below this line
 * @group Portable
 */
class VariantFacetQueryBuilderTest extends Unit
{
    public function testBuildReturnsNullForEmptySelections(): void
    {
        // Arrange
        $queryBuilder = new VariantFacetQueryBuilder(new VariantFacetsConfig());

        // Act
        $result = $queryBuilder->build([]);

        // Assert
        $this->assertNull($result);
    }

    /**
     * The headline P0/P3 case: two DIFFERENT selected facets must land inside the SAME nested `bool`
     * query — that single shared clause is what makes a concrete satisfy both at once, instead of core's
     * default of one independent nested query per facet.
     */
    public function testBuildCombinesMultipleValsSelectionsIntoOneSharedNestedBoolQuery(): void
    {
        // Arrange
        $queryBuilder = new VariantFacetQueryBuilder(new VariantFacetsConfig());
        $selections = [
            'limitrange' => ['scope' => 'vals', 'value' => '90°C'],
            'packaging_unit' => ['scope' => 'vals', 'value' => 'Box'],
        ];

        // Act
        $nested = $queryBuilder->build($selections);

        // Assert
        $this->assertNotNull($nested);
        $array = $nested->toArray();
        $this->assertSame('variant-facet', $array['nested']['path']);

        $filters = $array['nested']['query']['bool']['filter'];
        $this->assertCount(2, $filters, 'Both selected facets must be filter clauses inside the ONE nested bool query.');

        $fieldPaths = array_map(static fn (array $clause): string => array_key_first($clause['term'] ?? $clause['terms'] ?? []), $filters);
        $this->assertContains('variant-facet.vals.limitrange.keyword', $fieldPaths);
        $this->assertContains('variant-facet.vals.packaging_unit.keyword', $fieldPaths);
    }

    public function testBuildUsesTermsQueryForMultiValuedSelection(): void
    {
        // Arrange
        $queryBuilder = new VariantFacetQueryBuilder(new VariantFacetsConfig());
        $selections = ['farbe' => ['scope' => 'vals', 'value' => ['red', 'blue']]];

        // Act
        $nested = $queryBuilder->build($selections);

        // Assert
        $array = $nested->toArray();
        $termsClause = $array['nested']['query']['bool']['filter'][0]['terms'];
        $this->assertSame(['red', 'blue'], $termsClause['variant-facet.vals.farbe.keyword']);
    }

    public function testBuildUsesRangeQueryForNumsSelection(): void
    {
        // Arrange
        $queryBuilder = new VariantFacetQueryBuilder(new VariantFacetsConfig());
        $selections = ['poweroutput' => ['scope' => 'nums', 'value' => ['min' => 40.0, 'max' => 48.0]]];

        // Act
        $nested = $queryBuilder->build($selections);

        // Assert
        $array = $nested->toArray();
        $rangeClause = $array['nested']['query']['bool']['filter'][0]['range'];
        $this->assertSame(['gte' => 40.0, 'lte' => 48.0], $rangeClause['variant-facet.nums.poweroutput']);
    }

    public function testBuildSkipsNumsSelectionWithNoMinOrMax(): void
    {
        // Arrange
        $queryBuilder = new VariantFacetQueryBuilder(new VariantFacetsConfig());
        $selections = ['poweroutput' => ['scope' => 'nums', 'value' => ['min' => null, 'max' => null]]];

        // Act
        $result = $queryBuilder->build($selections);

        // Assert
        $this->assertNull($result, 'A range selection with neither bound set contributes nothing.');
    }

    public function testBuildDoesNotRequestInnerHitsByDefault(): void
    {
        // Arrange
        $queryBuilder = new VariantFacetQueryBuilder(new VariantFacetsConfig());
        $selections = ['limitrange' => ['scope' => 'vals', 'value' => '90°C']];

        // Act
        $nested = $queryBuilder->build($selections);

        // Assert
        $this->assertArrayNotHasKey('inner_hits', $nested->toArray()['nested'], 'Tile-swap is off by default (VariantFacetsConfig::isMatchingVariantTileSwapEnabled() === false).');
    }

    public function testBuildRequestsInnerHitsWhenTileSwapEnabled(): void
    {
        // Arrange
        $config = new class extends VariantFacetsConfig {
            public function isMatchingVariantTileSwapEnabled(): bool
            {
                return true;
            }
        };
        $queryBuilder = new VariantFacetQueryBuilder($config);
        $selections = ['limitrange' => ['scope' => 'vals', 'value' => '90°C']];

        // Act
        $nested = $queryBuilder->build($selections);

        // Assert
        $innerHits = $nested->toArray()['nested']['inner_hits'];
        $this->assertSame('variant-facet', $innerHits['name']);
    }

    public function testBuildInnerBoolQueryHasNoNestedWrapper(): void
    {
        // Arrange
        $queryBuilder = new VariantFacetQueryBuilder(new VariantFacetsConfig());
        $selections = ['limitrange' => ['scope' => 'vals', 'value' => '90°C']];

        // Act
        $boolQuery = $queryBuilder->buildInnerBoolQuery($selections);

        // Assert
        $this->assertNotNull($boolQuery);
        $this->assertArrayHasKey('bool', $boolQuery->toArray());
        $this->assertArrayNotHasKey('nested', $boolQuery->toArray());
    }
}
