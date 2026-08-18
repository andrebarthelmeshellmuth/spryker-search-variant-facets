<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\VariantFacets\AggregationExtractor;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\FacetConfigTransfer;
use SprykerCommunity\Client\VariantFacets\Aggregation\VariantFacetAggregationBuilder;
use SprykerCommunity\Client\VariantFacets\AggregationExtractor\VariantRangeExtractor;
use SprykerCommunity\Client\VariantFacets\Query\VariantFacetQueryBuilder;
use SprykerCommunity\Client\VariantFacets\VariantFacetsConfig;

/**
 * Fixture shapes below mirror this package's own README/fixture claim for HP-ECO-45K's leadtime_days
 * range facet (min 10, max 21 — see fixtures/apply.php).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group VariantFacets
 * @group AggregationExtractor
 * @group VariantRangeExtractorTest
 * Add your own group annotations below this line
 * @group Portable
 */
class VariantRangeExtractorTest extends Unit
{
    public function testExtractReadsUnselectedRangeShape(): void
    {
        // Arrange
        $extractor = $this->createExtractor();
        $facetConfigTransfer = (new FacetConfigTransfer())->setName('leadtime_days')->setParameterName('leadtime_days');
        $aggregations = [
            'variant-nested-leadtime_days' => [
                'variant-values-leadtime_days' => ['min' => 10.0, 'max' => 21.0],
            ],
        ];

        // Act
        $result = $extractor->extract($facetConfigTransfer, false, $aggregations, []);

        // Assert
        $this->assertSame(10, $result->getMin());
        $this->assertSame(21, $result->getMax());
    }

    public function testExtractReadsSelectedRangeGlobalShape(): void
    {
        // Arrange
        $extractor = $this->createExtractor();
        $facetConfigTransfer = (new FacetConfigTransfer())->setName('leadtime_days')->setParameterName('leadtime_days');
        $aggregations = [
            'variant-global-leadtime_days' => [
                'variant-root-filter-leadtime_days' => [
                    'variant-nested-leadtime_days' => [
                        'variant-values-leadtime_days' => ['min' => 10.0, 'max' => 21.0],
                    ],
                ],
            ],
        ];

        // Act
        $result = $extractor->extract($facetConfigTransfer, true, $aggregations, []);

        // Assert
        $this->assertSame(10, $result->getMin());
        $this->assertSame(21, $result->getMax());
    }

    public function testExtractReadsFilteredValuesShapeUnderAnInnerFilter(): void
    {
        // Arrange
        $extractor = $this->createExtractor();
        $facetConfigTransfer = (new FacetConfigTransfer())->setName('leadtime_days')->setParameterName('leadtime_days');
        $aggregations = [
            'variant-nested-leadtime_days' => [
                'variant-inner-filter-leadtime_days' => [
                    'variant-values-leadtime_days' => ['min' => 14.0, 'max' => 21.0],
                ],
            ],
        ];

        // Act
        $result = $extractor->extract($facetConfigTransfer, false, $aggregations, []);

        // Assert
        $this->assertSame(14, $result->getMin());
        $this->assertSame(21, $result->getMax());
    }

    public function testExtractDefaultsActiveMinAndMaxToTheFullRange(): void
    {
        // Arrange
        $extractor = $this->createExtractor();
        $facetConfigTransfer = (new FacetConfigTransfer())->setName('leadtime_days')->setParameterName('leadtime_days');
        $aggregations = [
            'variant-nested-leadtime_days' => [
                'variant-values-leadtime_days' => ['min' => 10.0, 'max' => 21.0],
            ],
        ];

        // Act
        $result = $extractor->extract($facetConfigTransfer, false, $aggregations, []);

        // Assert
        $this->assertSame(10, $result->getActiveMin());
        $this->assertSame(21, $result->getActiveMax());
    }

    public function testExtractReadsActiveMinAndMaxFromRequestParameters(): void
    {
        // Arrange
        $extractor = $this->createExtractor();
        $facetConfigTransfer = (new FacetConfigTransfer())->setName('leadtime_days')->setParameterName('leadtime_days');
        $aggregations = [
            'variant-nested-leadtime_days' => [
                'variant-values-leadtime_days' => ['min' => 10.0, 'max' => 21.0],
            ],
        ];

        // Act
        $result = $extractor->extract($facetConfigTransfer, false, $aggregations, ['leadtime_days' => ['min' => 12, 'max' => 18]]);

        // Assert
        $this->assertSame(12, $result->getActiveMin());
        $this->assertSame(18, $result->getActiveMax());
    }

    public function testExtractReturnsZeroMinAndMaxWhenAggregationIsMissing(): void
    {
        // Arrange
        $extractor = $this->createExtractor();
        $facetConfigTransfer = (new FacetConfigTransfer())->setName('leadtime_days')->setParameterName('leadtime_days');

        // Act
        $result = $extractor->extract($facetConfigTransfer, false, [], []);

        // Assert
        $this->assertSame(0, $result->getMin());
        $this->assertSame(0, $result->getMax());
    }

    protected function createExtractor(): VariantRangeExtractor
    {
        $aggregationBuilder = new VariantFacetAggregationBuilder(new VariantFacetQueryBuilder(new VariantFacetsConfig()), 10);

        return new VariantRangeExtractor($aggregationBuilder);
    }
}
