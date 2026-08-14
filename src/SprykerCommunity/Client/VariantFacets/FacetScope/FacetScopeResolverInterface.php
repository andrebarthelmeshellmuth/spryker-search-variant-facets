<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\FacetScope;

interface FacetScopeResolverInterface
{
    /**
     * Specification:
     * - Returns 'vals' if the facet is variant-scoped as a string facet, 'nums' if variant-scoped as a
     *   numeric/range facet, or null if it is not variant-scoped at all (core's normal per-facet
     *   OR-across-concretes handling applies).
     * - `VariantFacetsConfig::getForcedVariantScopedFacetNames()`/`getForcedNonVariantScopedFacetNames()`
     *   take priority over the live index mapping when a facet name appears in either.
     * - `null` in is always `null` out — `FacetConfigTransfer::getName()` is nullable in the generated
     *   transfer even though every real facet config has one; a nameless config can't be variant-scoped.
     *
     * @param string|null $facetName
     */
    public function resolveScope(?string $facetName): ?string;
}
