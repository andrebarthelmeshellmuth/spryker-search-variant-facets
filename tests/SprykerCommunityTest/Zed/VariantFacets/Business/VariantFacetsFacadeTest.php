<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\VariantFacets\Business;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\VariantFacets\Business\Attribute\VariantAttributeResolverInterface;
use SprykerCommunity\Zed\VariantFacets\Business\VariantFacetsBusinessFactory;
use SprykerCommunity\Zed\VariantFacets\Business\VariantFacetsFacade;

/**
 * `resolveVariantAttributes()` really delegates to the factory-built `VariantAttributeResolver` and
 * returns exactly what it returns, unmodified -- `VariantAttributeResolverTest` already covers the
 * resolver's own real logic, so this test's job is only the one hop above it.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group VariantFacets
 * @group Business
 * @group VariantFacetsFacadeTest
 * @group Portable
 */
class VariantFacetsFacadeTest extends Unit
{
    public function testResolveVariantAttributesDelegatesToTheFactoryBuiltResolverAndReturnsItsResultUnmodified(): void
    {
        // Arrange
        $spyProducts = [['id_product' => 1, 'sku' => 'shoe-red-40']];
        $result = [1 => ['sku' => 'shoe-red-40', 'vals' => ['color' => 'red'], 'nums' => ['size' => 40.0]]];

        $resolverMock = $this->createMock(VariantAttributeResolverInterface::class);
        $resolverMock->method('resolveVariantAttributes')->with($spyProducts, 66)->willReturn($result);

        $factoryMock = $this->createMock(VariantFacetsBusinessFactory::class);
        $factoryMock->method('createVariantAttributeResolver')->willReturn($resolverMock);

        $facade = new VariantFacetsFacade();
        $facade->setFactory($factoryMock);

        // Act & Assert
        $this->assertSame($result, $facade->resolveVariantAttributes($spyProducts, 66));
    }
}
