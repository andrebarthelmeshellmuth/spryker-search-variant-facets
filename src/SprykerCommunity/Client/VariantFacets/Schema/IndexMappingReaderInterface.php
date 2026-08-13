<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\Schema;

interface IndexMappingReaderInterface
{
    /**
     * Specification:
     * - Returns the set of facet names currently mapped under `variant-facet.vals.*` (string facets).
     * - Fails soft (empty set) if the index is unreachable or the field doesn't exist yet — a project
     *   that hasn't republished after installing this package should see every facet fall back to core's
     *   normal OR-across-concretes behavior, not an error.
     *
     * @return array<string>
     */
    public function getVariantScopedStringFacetNames(): array;

    /**
     * Specification:
     * - Same as {@link getVariantScopedStringFacetNames()} but for `variant-facet.nums.*` (range facets).
     *
     * @return array<string>
     */
    public function getVariantScopedNumericFacetNames(): array;
}
