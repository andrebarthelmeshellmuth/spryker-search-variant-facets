<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\VariantFacets\Business\Attribute;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ProductSearchAttributeCollectionTransfer;
use Generated\Shared\Transfer\ProductSearchAttributeTransfer;
use SprykerCommunity\Zed\VariantFacets\Business\Attribute\VariantAttributeResolver;
use SprykerCommunity\Zed\VariantFacets\Dependency\Facade\VariantFacetsToProductFacadeInterface;
use SprykerCommunity\Zed\VariantFacets\Dependency\Facade\VariantFacetsToProductSearchFacadeInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group VariantFacets
 * @group Business
 * @group Attribute
 * @group VariantAttributeResolverTest
 * Add your own group annotations below this line
 */
class VariantAttributeResolverTest extends Unit
{
    /**
     * The exact P2/P0 STL-7010 headline case: two search-excluded concretes (90°C/Box, 130°C/Item) must
     * be absent from the result entirely, and the remaining four must carry exactly their own values.
     */
    public function testResolveVariantAttributesSkipsNonSearchableConcretesAndReturnsOwnValuesPerConcrete(): void
    {
        // Arrange
        $resolver = $this->createResolver([
            'limitrange' => 'multi-select',
            'packaging_unit' => 'multi-select',
        ]);

        $spyProducts = [
            $this->createSpyProduct('STL-7010-1', true, ['limitrange' => '90°C', 'packaging_unit' => 'Item']),
            $this->createSpyProduct('STL-7010-3', false, ['limitrange' => '90°C', 'packaging_unit' => 'Box']),
            $this->createSpyProduct('STL-7010-6', true, ['limitrange' => '130°C', 'packaging_unit' => 'Box']),
        ];

        // Act
        $result = $resolver->resolveVariantAttributes($spyProducts, 66);

        // Assert
        $this->assertCount(2, $result, 'The non-searchable concrete (STL-7010-3) must be excluded entirely.');
        $skus = array_column($result, 'sku');
        $this->assertSame(['STL-7010-1', 'STL-7010-6'], $skus);
        $this->assertSame(['limitrange' => '90°C', 'packaging_unit' => 'Item'], $result[0]['vals']);
        $this->assertSame(['limitrange' => '130°C', 'packaging_unit' => 'Box'], $result[1]['vals']);
    }

    public function testResolveVariantAttributesOnlyKeepsConfiguredFacetKeys(): void
    {
        // Arrange: "housingmaterial" is a real attribute on the concrete but NOT configured as a facet.
        $resolver = $this->createResolver(['limitrange' => 'multi-select']);
        $spyProducts = [
            $this->createSpyProduct('STL-7010-1', true, ['limitrange' => '90°C', 'housingmaterial' => 'Plastic']),
        ];

        // Act
        $result = $resolver->resolveVariantAttributes($spyProducts, 66);

        // Assert
        $this->assertSame(['limitrange' => '90°C'], $result[0]['vals']);
    }

    /**
     * A `product_management_attribute.is_multiple` attribute (real, configured for `farbe` in this
     * project) can hold more than one value on a single concrete at once (a striped shoe that's red AND
     * green) — decoded as a JSON array. Must be written through as an array of strings, not collapsed to
     * a single string (PHP casts an array to the literal string "Array", silently corrupting the facet
     * value).
     */
    public function testResolveVariantAttributesKeepsMultiValuedAttributeAsArrayOfStrings(): void
    {
        // Arrange
        $resolver = $this->createResolver(['farbe' => 'multi-select']);
        $spyProducts = [
            $this->createSpyProduct('SHOE-1', true, ['farbe' => ['red', 'green']]),
        ];

        // Act
        $result = $resolver->resolveVariantAttributes($spyProducts, 66);

        // Assert
        $this->assertSame(['farbe' => ['red', 'green']], $result[0]['vals']);
    }

    public function testResolveVariantAttributesRoutesRangeFilterTypeToNumsAsFloat(): void
    {
        // Arrange
        $resolver = $this->createResolver(['poweroutput' => 'range']);
        $spyProducts = [
            $this->createSpyProduct('HP-ECO-45K-1', true, ['poweroutput' => '45']),
        ];

        // Act
        $result = $resolver->resolveVariantAttributes($spyProducts, 66);

        // Assert
        $this->assertSame([], $result[0]['vals']);
        $this->assertSame(['poweroutput' => 45.0], $result[0]['nums']);
        $this->assertIsFloat($result[0]['nums']['poweroutput']);
    }

    public function testResolveVariantAttributesDropsNonNumericValueForRangeFilterType(): void
    {
        // Arrange: a range-configured attribute whose actual value isn't numeric (misconfigured data) —
        // must be dropped, not crash or be miscast.
        $resolver = $this->createResolver(['poweroutput' => 'range']);
        $spyProducts = [
            $this->createSpyProduct('X-1', true, ['poweroutput' => '45 kW']),
        ];

        // Act
        $result = $resolver->resolveVariantAttributes($spyProducts, 66);

        // Assert
        $this->assertSame([], $result, 'A concrete with no usable facet-relevant attribute contributes nothing.');
    }

    public function testResolveVariantAttributesSkipsConcreteWithNoConfiguredAttributesAtAll(): void
    {
        // Arrange
        $resolver = $this->createResolver(['limitrange' => 'multi-select']);
        $spyProducts = [
            $this->createSpyProduct('OTHER-1', true, ['brand' => 'Topstar']),
        ];

        // Act
        $result = $resolver->resolveVariantAttributes($spyProducts, 66);

        // Assert
        $this->assertSame([], $result);
    }

    public function testResolveVariantAttributesRejectsFacetKeyThatFailsTheNamePattern(): void
    {
        // Arrange: a facet key containing a dot could be misread as a field-path separator downstream —
        // FACET_NAME_PATTERN rejects it defensively even though this shouldn't occur in real config.
        $resolver = $this->createResolver(['bad.key' => 'multi-select']);
        $spyProducts = [
            $this->createSpyProduct('X-1', true, ['bad.key' => 'value']),
        ];

        // Act
        $result = $resolver->resolveVariantAttributes($spyProducts, 66);

        // Assert
        $this->assertSame([], $result);
    }

    public function testResolveVariantAttributesReturnsEmptyWhenNoFacetsAreConfiguredAtAll(): void
    {
        // Arrange
        $resolver = $this->createResolver([]);
        $spyProducts = [$this->createSpyProduct('X-1', true, ['limitrange' => '90°C'])];

        // Act
        $result = $resolver->resolveVariantAttributes($spyProducts, 66);

        // Assert
        $this->assertSame([], $result, 'No configured facets at all is a fast exit, not one skipped concrete at a time.');
    }

    /**
     * @param array<string, string> $filterTypeByKey Attribute key => product_search_attribute filter_type.
     */
    protected function createResolver(array $filterTypeByKey): VariantAttributeResolver
    {
        $productSearchAttributeTransfers = [];

        foreach ($filterTypeByKey as $key => $filterType) {
            $productSearchAttributeTransfers[] = (new ProductSearchAttributeTransfer())
                ->setKey($key)
                ->setFilterType($filterType);
        }

        $collectionTransfer = new ProductSearchAttributeCollectionTransfer();

        foreach ($productSearchAttributeTransfers as $transfer) {
            $collectionTransfer->addProductSearchAttribute($transfer);
        }

        $productSearchFacadeMock = $this->createMock(VariantFacetsToProductSearchFacadeInterface::class);
        $productSearchFacadeMock->method('getProductSearchAttributeCollection')->willReturn($collectionTransfer);

        $productFacadeMock = $this->createMock(VariantFacetsToProductFacadeInterface::class);
        $productFacadeMock->method('decodeProductAttributes')->willReturnCallback(
            static fn (string $attributes): array => json_decode($attributes, true) ?? [],
        );

        return new VariantAttributeResolver($productSearchFacadeMock, $productFacadeMock);
    }

    /**
     * @param string $sku
     * @param bool $isSearchable
     * @param array<string, string> $attributes
     *
     * @return array<string, mixed>
     */
    protected function createSpyProduct(string $sku, bool $isSearchable, array $attributes): array
    {
        return [
            'sku' => $sku,
            'attributes' => json_encode($attributes),
            'SpyProductSearches' => [
                ['fk_locale' => 66, 'is_searchable' => $isSearchable],
            ],
        ];
    }
}
