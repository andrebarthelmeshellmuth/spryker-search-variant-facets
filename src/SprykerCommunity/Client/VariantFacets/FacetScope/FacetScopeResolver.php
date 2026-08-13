<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\FacetScope;

use SprykerCommunity\Client\VariantFacets\VariantFacetsConfig;

/**
 * Composes the config-level force lists (`VariantFacetsConfig::getForcedVariantScopedFacetNames()`/
 * `getForcedNonVariantScopedFacetNames()`) with the live index-mapping auto-detection
 * (`IndexMappingFacetScopeStrategy`), the default and normally-authoritative source.
 */
class FacetScopeResolver implements FacetScopeResolverInterface
{
    public function __construct(
        protected FacetScopeResolverInterface $indexMappingFacetScopeStrategy,
        protected VariantFacetsConfig $config,
    ) {
    }

    public function resolveScope(?string $facetName): ?string
    {
        if ($facetName === null) {
            return null;
        }

        if (in_array($facetName, $this->config->getForcedNonVariantScopedFacetNames(), true)) {
            return null;
        }

        $mappingScope = $this->indexMappingFacetScopeStrategy->resolveScope($facetName);

        if ($mappingScope !== null) {
            return $mappingScope;
        }

        if (in_array($facetName, $this->config->getForcedVariantScopedFacetNames(), true)) {
            return IndexMappingFacetScopeStrategy::SCOPE_VALS;
        }

        return null;
    }
}
