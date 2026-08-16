<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\VariantFacets\FacetScope;

use Codeception\Test\Unit;
use SprykerCommunity\Client\VariantFacets\FacetScope\IndexMappingFacetScopeStrategy;
use SprykerCommunity\Client\VariantFacets\Schema\IndexMappingReaderInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group VariantFacets
 * @group FacetScope
 * @group IndexMappingFacetScopeStrategyTest
 * Add your own group annotations below this line
 * @group Portable
 */
class IndexMappingFacetScopeStrategyTest extends Unit
{
    public function testResolveScopeReturnsNullForNullFacetName(): void
    {
        // Arrange
        $indexMappingReader = $this->createMock(IndexMappingReaderInterface::class);
        $strategy = new IndexMappingFacetScopeStrategy($indexMappingReader);

        // Act & Assert
        $this->assertNull($strategy->resolveScope(null));
    }

    public function testResolveScopeReturnsValsWhenFacetIsAStringFacetInTheMapping(): void
    {
        // Arrange
        $indexMappingReader = $this->createMock(IndexMappingReaderInterface::class);
        $indexMappingReader->method('getVariantScopedStringFacetNames')->willReturn(['limitrange', 'packaging_unit']);
        $indexMappingReader->method('getVariantScopedNumericFacetNames')->willReturn([]);
        $strategy = new IndexMappingFacetScopeStrategy($indexMappingReader);

        // Act & Assert
        $this->assertSame(IndexMappingFacetScopeStrategy::SCOPE_VALS, $strategy->resolveScope('limitrange'));
    }

    public function testResolveScopeReturnsNumsWhenFacetIsANumericFacetInTheMapping(): void
    {
        // Arrange
        $indexMappingReader = $this->createMock(IndexMappingReaderInterface::class);
        $indexMappingReader->method('getVariantScopedStringFacetNames')->willReturn([]);
        $indexMappingReader->method('getVariantScopedNumericFacetNames')->willReturn(['leadtime_days']);
        $strategy = new IndexMappingFacetScopeStrategy($indexMappingReader);

        // Act & Assert
        $this->assertSame(IndexMappingFacetScopeStrategy::SCOPE_NUMS, $strategy->resolveScope('leadtime_days'));
    }

    public function testResolveScopeReturnsNullWhenFacetIsInNeitherMappedSet(): void
    {
        // Arrange
        $indexMappingReader = $this->createMock(IndexMappingReaderInterface::class);
        $indexMappingReader->method('getVariantScopedStringFacetNames')->willReturn(['limitrange']);
        $indexMappingReader->method('getVariantScopedNumericFacetNames')->willReturn(['leadtime_days']);
        $strategy = new IndexMappingFacetScopeStrategy($indexMappingReader);

        // Act & Assert
        $this->assertNull($strategy->resolveScope('brand'), 'A facet the live mapping has never seen as variant-scoped falls through to core\'s normal handling.');
    }
}
