<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\VariantFacets\Plugin\Catalog;

use Codeception\Test\Unit;
use Elastica\Result;
use Elastica\ResultSet;
use Generated\Shared\Search\PageIndexMap;
use SprykerCommunity\Client\VariantFacets\Plugin\Catalog\MatchingVariantResultFormatterPlugin;
use SprykerCommunity\Client\VariantFacets\VariantFacetsConfig;
use SprykerCommunity\Client\VariantFacets\VariantFacetsFactory;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group VariantFacets
 * @group Plugin
 * @group Catalog
 * @group MatchingVariantResultFormatterPluginTest
 * Add your own group annotations below this line
 */
class MatchingVariantResultFormatterPluginTest extends Unit
{
    public function testFormatResultReturnsEmptyArrayWhenTileSwapIsDisabled(): void
    {
        // Arrange
        $plugin = $this->createPlugin(false);
        $resultSet = $this->createResultSet([
            $this->createHit(1, ['variant-facet' => ['hits' => ['hits' => [['_source' => ['sku' => 'STL-7010-2']]]]]]),
        ]);

        // Act
        $result = $plugin->formatResult($resultSet, []);

        // Assert
        $this->assertSame([], $result, 'Tile-swap is off by default — no inner_hits were requested, so there is nothing to read.');
    }

    public function testFormatResultKeysMatchingSkusByIdProductAbstractWhenEnabled(): void
    {
        // Arrange
        $plugin = $this->createPlugin(true);
        $innerHitDocuments = [
            ['_source' => ['sku' => 'STL-7010-2']],
            ['_source' => ['sku' => 'STL-7010-5']],
        ];
        $innerHits = ['variant-facet' => ['hits' => ['hits' => $innerHitDocuments]]];
        $resultSet = $this->createResultSet([$this->createHit(1, $innerHits)]);

        // Act
        $result = $plugin->formatResult($resultSet, []);

        // Assert
        $this->assertSame([1 => ['STL-7010-2', 'STL-7010-5']], $result);
    }

    public function testFormatResultSkipsDocumentsWithNoInnerHits(): void
    {
        // Arrange
        $plugin = $this->createPlugin(true);
        $resultSet = $this->createResultSet([
            $this->createHit(1, null),
        ]);

        // Act
        $result = $plugin->formatResult($resultSet, []);

        // Assert
        $this->assertSame([], $result, 'A product whose search request never asked for inner_hits (e.g. no variant facet selected) has nothing to swap.');
    }

    public function testFormatResultSkipsDocumentsWithNoIdProductAbstract(): void
    {
        // Arrange
        $plugin = $this->createPlugin(true);
        $hit = [
            '_source' => [],
            'inner_hits' => ['variant-facet' => ['hits' => ['hits' => [['_source' => ['sku' => 'STL-7010-2']]]]]],
        ];
        $resultSet = $this->createResultSet([new Result($hit)]);

        // Act
        $result = $plugin->formatResult($resultSet, []);

        // Assert
        $this->assertSame([], $result);
    }

    public function testFormatResultCoversMultipleProducts(): void
    {
        // Arrange
        $plugin = $this->createPlugin(true);
        $resultSet = $this->createResultSet([
            $this->createHit(1, ['variant-facet' => ['hits' => ['hits' => [['_source' => ['sku' => 'STL-7010-2']]]]]]),
            $this->createHit(2, ['variant-facet' => ['hits' => ['hits' => [['_source' => ['sku' => 'HP-ECO-45K-1']]]]]]),
        ]);

        // Act
        $result = $plugin->formatResult($resultSet, []);

        // Assert
        $this->assertSame([1 => ['STL-7010-2'], 2 => ['HP-ECO-45K-1']], $result);
    }

    protected function createPlugin(bool $isMatchingVariantTileSwapEnabled): MatchingVariantResultFormatterPlugin
    {
        $config = new class ($isMatchingVariantTileSwapEnabled) extends VariantFacetsConfig {
            public function __construct(protected bool $isMatchingVariantTileSwapEnabled)
            {
            }

            public function isMatchingVariantTileSwapEnabled(): bool
            {
                return $this->isMatchingVariantTileSwapEnabled;
            }
        };

        $factory = $this->getMockBuilder(VariantFacetsFactory::class)->onlyMethods(['getConfig'])->getMock();
        $factory->method('getConfig')->willReturn($config);

        $plugin = new MatchingVariantResultFormatterPlugin();
        $plugin->setFactory($factory);

        return $plugin;
    }

    /**
     * @param array<int, \Elastica\Result> $results
     */
    protected function createResultSet(array $results): ResultSet
    {
        $resultSet = $this->getMockBuilder(ResultSet::class)->disableOriginalConstructor()->getMock();
        $resultSet->method('getResults')->willReturn($results);

        return $resultSet;
    }

    /**
     * @param int|null $idProductAbstract
     * @param array<string, mixed>|null $innerHits
     */
    protected function createHit(?int $idProductAbstract, ?array $innerHits): Result
    {
        $hit = [
            '_source' => $idProductAbstract === null ? [] : [PageIndexMap::SEARCH_RESULT_DATA => ['id_product_abstract' => $idProductAbstract]],
        ];

        if ($innerHits !== null) {
            $hit['inner_hits'] = $innerHits;
        }

        return new Result($hit);
    }
}
