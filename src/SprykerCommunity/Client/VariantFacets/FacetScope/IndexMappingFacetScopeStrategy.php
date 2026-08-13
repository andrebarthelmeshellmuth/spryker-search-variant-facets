<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\FacetScope;

use SprykerCommunity\Client\VariantFacets\Schema\IndexMappingReaderInterface;

class IndexMappingFacetScopeStrategy implements FacetScopeResolverInterface
{
    /**
     * @var string
     */
    public const SCOPE_VALS = 'vals';

    /**
     * @var string
     */
    public const SCOPE_NUMS = 'nums';

    public function __construct(protected IndexMappingReaderInterface $indexMappingReader)
    {
    }

    public function resolveScope(?string $facetName): ?string
    {
        if ($facetName === null) {
            return null;
        }

        if (in_array($facetName, $this->indexMappingReader->getVariantScopedStringFacetNames(), true)) {
            return static::SCOPE_VALS;
        }

        if (in_array($facetName, $this->indexMappingReader->getVariantScopedNumericFacetNames(), true)) {
            return static::SCOPE_NUMS;
        }

        return null;
    }
}
