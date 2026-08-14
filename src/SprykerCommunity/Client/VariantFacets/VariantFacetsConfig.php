<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets;

use Spryker\Client\Kernel\AbstractBundleConfig;

class VariantFacetsConfig extends AbstractBundleConfig
{
    /**
     * @var string
     */
    protected const SOURCE_IDENTIFIER_PAGE = 'page';

    public function getPageSourceIdentifier(): string
    {
        return static::SOURCE_IDENTIFIER_PAGE;
    }

    /**
     * Explicit override for facet names that should ALWAYS be treated as variant-scoped, regardless of
     * what the live index mapping shows (e.g. a project that hasn't republished yet, or wants to force
     * the behavior for a facet not yet indexed). Empty by default — auto-detection from the live
     * `variant-facet.vals.*`/`variant-facet.nums.*` mapping is authoritative unless a project overrides
     * this.
     *
     * @return array<string>
     */
    public function getForcedVariantScopedFacetNames(): array
    {
        return [];
    }

    /**
     * Explicit override for facet names that should NEVER be treated as variant-scoped, even if a name
     * collision or unexpected mapping state would otherwise suggest it. Empty by default.
     *
     * @return array<string>
     */
    public function getForcedNonVariantScopedFacetNames(): array
    {
        return [];
    }

    /**
     * Whether to request `inner_hits` on the combined variant-facet query and expose which concrete(s)
     * actually matched (`MatchingVariantResultFormatterPlugin`) — the data a storefront tile-swap widget
     * needs. Off by default: it's an additive storefront feature, not required for the AND-filter/facet-
     * count fix itself, and costs a small amount of extra response payload per hit. Override in a
     * project's own `Pyz\Client\VariantFacets\VariantFacetsConfig` to enable.
     *
     * @api
     */
    public function isMatchingVariantTileSwapEnabled(): bool
    {
        return false;
    }

    /**
     * Whether to hide a variant-scoped facet (or, for a range facet, its slider) when it genuinely
     * cannot narrow the current result set any further — ported from Produktkonfigurator's
     * `isFacetUseful()`, made precise by this package's exact per-concrete counts (under core's
     * OR-across-concretes counts, "this doesn't narrow anything" was only ever an approximation).
     * Off by default: this is a UX opinion (hide noise vs. show every option), not a correctness fix, and
     * whether it's right depends on the audience (a B2B/professional buyer may want to see every
     * attribute value regardless of whether it currently narrows). See the README's "Facet usefulness
     * filtering" section for the exact rule and the reasoning for defaulting to off.
     *
     * @api
     */
    public function isUselessFacetFilteringEnabled(): bool
    {
        return false;
    }
}
