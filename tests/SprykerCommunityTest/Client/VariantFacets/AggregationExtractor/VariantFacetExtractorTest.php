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
use SprykerCommunity\Client\VariantFacets\AggregationExtractor\VariantFacetExtractor;
use SprykerCommunity\Client\VariantFacets\Query\VariantFacetQueryBuilder;
use SprykerCommunity\Client\VariantFacets\VariantFacetsConfig;

/**
 * Fixture shapes below are the exact P0 spike / P4 live-verified aggregation response structure for
 * `packaging_unit` with `limitrange=90°C` selected: `Item:1`, `5-pack:1` — `Box` correctly excluded
 * because no active concrete combines 90°C with Box packaging (see search-variant-facets-plan memory).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group VariantFacets
 * @group AggregationExtractor
 * @group VariantFacetExtractorTest
 * Add your own group annotations below this line
 * @group Portable
 */
class VariantFacetExtractorTest extends Unit
{
    public function testExtractReadsUnselectedFacetShape(): void
    {
        // Arrange
        $extractor = $this->createExtractor();
        $facetConfigTransfer = (new FacetConfigTransfer())->setName('packaging_unit')->setParameterName('packaging_unit');
        $aggregations = [
            'variant-nested-packaging_unit' => [
                'variant-inner-filter-packaging_unit' => [
                    'variant-values-packaging_unit' => [
                        'buckets' => [
                            ['key' => 'Item', 'doc_count' => 1, 'variant-root-packaging_unit' => ['doc_count' => 1]],
                            ['key' => '5-pack', 'doc_count' => 1, 'variant-root-packaging_unit' => ['doc_count' => 1]],
                            ['key' => 'Box', 'doc_count' => 0, 'variant-root-packaging_unit' => ['doc_count' => 0]],
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $result = $extractor->extract($facetConfigTransfer, false, $aggregations, []);

        // Assert
        $values = [];

        foreach ($result->getValues() as $value) {
            $values[$value->getValue()] = $value->getDocCount();
        }

        $this->assertSame(['Item' => 1, '5-pack' => 1], $values, 'Box (doc_count 0) must be dropped — it cannot narrow the result any further.');
    }

    public function testExtractReadsSelectedFacetGlobalShape(): void
    {
        // Arrange
        $extractor = $this->createExtractor();
        $facetConfigTransfer = (new FacetConfigTransfer())->setName('limitrange')->setParameterName('limitrange');
        $aggregations = [
            'variant-global-limitrange' => [
                'variant-root-filter-limitrange' => [
                    'variant-nested-limitrange' => [
                        'variant-values-limitrange' => [
                            'buckets' => [
                                ['key' => '90°C', 'doc_count' => 1, 'variant-root-limitrange' => ['doc_count' => 1]],
                                ['key' => '130°C', 'doc_count' => 1, 'variant-root-limitrange' => ['doc_count' => 1]],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $result = $extractor->extract($facetConfigTransfer, true, $aggregations, ['limitrange' => '90°C']);

        // Assert
        $values = [];

        foreach ($result->getValues() as $value) {
            $values[$value->getValue()] = $value->getDocCount();
        }

        $this->assertSame(['90°C' => 1, '130°C' => 1], $values, 'The selected facet keeps showing its OWN alternative values, ignoring its own filter.');
        $this->assertSame('90°C', $result->getActiveValue());
    }

    public function testExtractReturnsEmptyValuesWhenAggregationIsMissing(): void
    {
        // Arrange
        $extractor = $this->createExtractor();
        $facetConfigTransfer = (new FacetConfigTransfer())->setName('limitrange')->setParameterName('limitrange');

        // Act
        $result = $extractor->extract($facetConfigTransfer, false, [], []);

        // Assert
        $this->assertCount(0, $result->getValues());
    }

    protected function createExtractor(): VariantFacetExtractor
    {
        $aggregationBuilder = new VariantFacetAggregationBuilder(new VariantFacetQueryBuilder(new VariantFacetsConfig()), 10);

        return new VariantFacetExtractor($aggregationBuilder);
    }
}
