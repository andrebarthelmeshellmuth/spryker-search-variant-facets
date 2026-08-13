<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\VariantFacets\FacetScope;

use Codeception\Test\Unit;
use SprykerCommunity\Client\VariantFacets\FacetScope\FacetScopeResolver;
use SprykerCommunity\Client\VariantFacets\FacetScope\FacetScopeResolverInterface;
use SprykerCommunity\Client\VariantFacets\FacetScope\IndexMappingFacetScopeStrategy;
use SprykerCommunity\Client\VariantFacets\VariantFacetsConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group VariantFacets
 * @group FacetScope
 * @group FacetScopeResolverTest
 * Add your own group annotations below this line
 */
class FacetScopeResolverTest extends Unit
{
    public function testResolveScopeReturnsNullForNullFacetName(): void
    {
        // Arrange
        $resolver = $this->createResolver($this->createAlwaysNullMappingStrategy(), new VariantFacetsConfig());

        // Act & Assert
        $this->assertNull($resolver->resolveScope(null));
    }

    public function testResolveScopeUsesLiveMappingByDefault(): void
    {
        // Arrange
        $mappingStrategy = $this->createStubMappingStrategy(['limitrange' => IndexMappingFacetScopeStrategy::SCOPE_VALS]);
        $resolver = $this->createResolver($mappingStrategy, new VariantFacetsConfig());

        // Act & Assert
        $this->assertSame(IndexMappingFacetScopeStrategy::SCOPE_VALS, $resolver->resolveScope('limitrange'));
        $this->assertNull($resolver->resolveScope('farbe'), 'A facet the live mapping does not know is not variant-scoped.');
    }

    public function testForcedNonVariantOverridesLiveMapping(): void
    {
        // Arrange
        $mappingStrategy = $this->createStubMappingStrategy(['limitrange' => IndexMappingFacetScopeStrategy::SCOPE_VALS]);
        $config = new class extends VariantFacetsConfig {
            public function getForcedNonVariantScopedFacetNames(): array
            {
                return ['limitrange'];
            }
        };
        $resolver = $this->createResolver($mappingStrategy, $config);

        // Act & Assert
        $this->assertNull($resolver->resolveScope('limitrange'), 'An explicit non-variant force wins even though the live mapping says vals.');
    }

    public function testForcedVariantIsOnlyAFallbackWhenMappingIsSilent(): void
    {
        // Arrange: mapping doesn't know "packaging_unit" yet (e.g. not republished).
        $mappingStrategy = $this->createStubMappingStrategy([]);
        $config = new class extends VariantFacetsConfig {
            public function getForcedVariantScopedFacetNames(): array
            {
                return ['packaging_unit'];
            }
        };
        $resolver = $this->createResolver($mappingStrategy, $config);

        // Act & Assert
        $this->assertSame(IndexMappingFacetScopeStrategy::SCOPE_VALS, $resolver->resolveScope('packaging_unit'));
    }

    public function testLiveMappingWinsOverForcedVariantWhenBothKnowTheFacet(): void
    {
        // Arrange: mapping says "nums" but the force list would default to "vals" — mapping must win.
        $mappingStrategy = $this->createStubMappingStrategy(['poweroutput' => IndexMappingFacetScopeStrategy::SCOPE_NUMS]);
        $config = new class extends VariantFacetsConfig {
            public function getForcedVariantScopedFacetNames(): array
            {
                return ['poweroutput'];
            }
        };
        $resolver = $this->createResolver($mappingStrategy, $config);

        // Act & Assert
        $this->assertSame(IndexMappingFacetScopeStrategy::SCOPE_NUMS, $resolver->resolveScope('poweroutput'));
    }

    /**
     * @param \SprykerCommunity\Client\VariantFacets\FacetScope\FacetScopeResolverInterface $mappingStrategy
     * @param \SprykerCommunity\Client\VariantFacets\VariantFacetsConfig $config
     */
    protected function createResolver(FacetScopeResolverInterface $mappingStrategy, VariantFacetsConfig $config): FacetScopeResolver
    {
        return new FacetScopeResolver($mappingStrategy, $config);
    }

    /**
     * @param array<string, string> $scopesByName
     */
    protected function createStubMappingStrategy(array $scopesByName): FacetScopeResolverInterface
    {
        return new class ($scopesByName) implements FacetScopeResolverInterface {
            public function __construct(protected array $scopesByName)
            {
            }

            public function resolveScope(?string $facetName): ?string
            {
                return $this->scopesByName[$facetName] ?? null;
            }
        };
    }

    protected function createAlwaysNullMappingStrategy(): FacetScopeResolverInterface
    {
        return $this->createStubMappingStrategy([]);
    }
}
