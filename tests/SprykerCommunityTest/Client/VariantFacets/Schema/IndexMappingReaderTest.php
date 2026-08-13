<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\VariantFacets\Schema;

use Codeception\Test\Unit;
use Elastica\Client;
use Elastica\Exception\Connection\HttpException;
use Elastica\Index;
use Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface;
use SprykerCommunity\Client\VariantFacets\Schema\IndexMappingReader;
use SprykerCommunity\Client\VariantFacets\VariantFacetsConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group VariantFacets
 * @group Schema
 * @group IndexMappingReaderTest
 * Add your own group annotations below this line
 */
class IndexMappingReaderTest extends Unit
{
    public function testGetVariantScopedStringFacetNamesReadsValsFromTheLiveMapping(): void
    {
        // Arrange
        $mapping = [
            'properties' => [
                'variant-facet' => [
                    'properties' => [
                        'vals' => ['properties' => ['limitrange' => ['type' => 'text'], 'packaging_unit' => ['type' => 'text']]],
                        'nums' => ['properties' => []],
                    ],
                ],
            ],
        ];
        $reader = $this->createReader('index_1', $mapping);

        // Act & Assert
        $this->assertSame(['limitrange', 'packaging_unit'], $reader->getVariantScopedStringFacetNames());
    }

    public function testGetVariantScopedNumericFacetNamesReadsNumsFromTheLiveMapping(): void
    {
        // Arrange
        $mapping = [
            'properties' => [
                'variant-facet' => [
                    'properties' => [
                        'vals' => ['properties' => []],
                        'nums' => ['properties' => ['leadtime_days' => ['type' => 'float']]],
                    ],
                ],
            ],
        ];
        $reader = $this->createReader('index_2', $mapping);

        // Act & Assert
        $this->assertSame(['leadtime_days'], $reader->getVariantScopedNumericFacetNames());
    }

    public function testReturnsEmptySetsWhenTheVariantFacetFieldDoesNotExistYet(): void
    {
        // Arrange
        $reader = $this->createReader('index_3', ['properties' => []]);

        // Act & Assert
        $this->assertSame([], $reader->getVariantScopedStringFacetNames(), 'Before the first republish, the mapping has no variant-facet field at all — every facet must fall back to core\'s normal handling, not error.');
        $this->assertSame([], $reader->getVariantScopedNumericFacetNames());
    }

    public function testFailsSoftWhenTheIndexIsUnreachable(): void
    {
        // Arrange
        $indexNameResolver = $this->createMock(IndexNameResolverInterface::class);
        $indexNameResolver->method('resolve')->willReturn('index_4');

        $index = $this->getMockBuilder(Index::class)->disableOriginalConstructor()->getMock();
        $index->method('getMapping')->willThrowException(new HttpException(CURLE_COULDNT_CONNECT));

        $elasticaClient = $this->getMockBuilder(Client::class)->disableOriginalConstructor()->getMock();
        $elasticaClient->method('getIndex')->willReturn($index);

        $reader = new IndexMappingReader($elasticaClient, $indexNameResolver, new VariantFacetsConfig());

        // Act & Assert
        $this->assertSame([], $reader->getVariantScopedStringFacetNames());
        $this->assertSame([], $reader->getVariantScopedNumericFacetNames());
    }

    public function testStringKeysOfAnAllDigitFacetNameStayStrings(): void
    {
        // A JSON-decoded object with all-numeric keys ("2", "3", ...) gets its keys cast to PHP ints by
        // json_decode() — array_keys() on that would silently hand back ints where this class promises
        // strings.
        // Arrange
        $mapping = [
            'properties' => [
                'variant-facet' => [
                    'properties' => [
                        'vals' => ['properties' => ['2' => ['type' => 'text'], '3' => ['type' => 'text']]],
                        'nums' => ['properties' => []],
                    ],
                ],
            ],
        ];
        $reader = $this->createReader('index_5', $mapping);

        // Act
        $names = $reader->getVariantScopedStringFacetNames();

        // Assert
        foreach ($names as $name) {
            $this->assertIsString($name);
        }
        $this->assertSame(['2', '3'], $names);
    }

    /**
     * A fresh index name per test — IndexMappingReader caches its result per resolved index name for
     * the lifetime of the process, so reusing one name across tests would silently serve a PRIOR test's
     * mapping instead of exercising this one.
     *
     * @param string $indexName
     * @param array<string, mixed> $mapping
     */
    protected function createReader(string $indexName, array $mapping): IndexMappingReader
    {
        $indexNameResolver = $this->createMock(IndexNameResolverInterface::class);
        $indexNameResolver->method('resolve')->willReturn($indexName);

        $index = $this->getMockBuilder(Index::class)->disableOriginalConstructor()->getMock();
        $index->method('getMapping')->willReturn($mapping);

        $elasticaClient = $this->getMockBuilder(Client::class)->disableOriginalConstructor()->getMock();
        $elasticaClient->method('getIndex')->willReturn($index);

        return new IndexMappingReader($elasticaClient, $indexNameResolver, new VariantFacetsConfig());
    }
}
