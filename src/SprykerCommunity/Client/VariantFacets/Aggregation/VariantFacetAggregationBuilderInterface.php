<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\Aggregation;

use Elastica\Aggregation\AbstractAggregation;
use Elastica\Query\BoolQuery;

interface VariantFacetAggregationBuilderInterface
{
    /**
     * The prefix `VariantFacetExtractor`/`VariantRangeExtractor` strip back off a `global` aggregation's
     * name to recover the facet name it belongs to — part of the actual contract between whatever builds
     * these aggregations and whatever reads their response back apart, not an implementation detail of
     * one specific builder, hence declared here rather than on a single implementation.
     *
     * @var string
     */
    public const AGGREGATION_GLOBAL_PREFIX = 'variant-global-';

    /**
     * Specification:
     * - Builds the "unselected facet" shape: `nested(variant-facet) > filter(other selected
     *   variant facets, if any) > terms(vals.<facetName>.keyword) > reverse_nested()`.
     * - Counts are ROOT DOCS (abstracts), via `reverse_nested` — so they never sum to the total hit
     *   count, since one abstract can carry more than one facet value across its concretes.
     * - Scoped by whatever the outer query already filtered to (base query + non-variant facet
     *   filters + the single combined nested filter `VariantFacetQueryBuilder` adds for all currently
     *   selected variant-scoped facets) — this aggregation does not need to redeclare those, only the
     *   per-concrete "other selected variant facets" constraint, which a nested aggregation does NOT
     *   inherit automatically from the outer query.
     *
     * @param string $facetName
     * @param array<string, array{scope: string, value: mixed}> $otherSelectedVariantSelections Currently
     *   selected variant-scoped facets, excluding `$facetName` itself.
     */
    public function buildUnselectedFacetAggregation(string $facetName, array $otherSelectedVariantSelections): AbstractAggregation;

    /**
     * Specification:
     * - Builds the "selected facet" shape: `global > filter(base query clone + other selected
     *   non-variant facet filters + other selected variant facets as one combined nested filter) >
     *   nested(variant-facet) > filter(other selected variant facets) > terms(vals.<facetName>.keyword) >
     *   reverse_nested()`.
     * - `global` bypasses the ENTIRE outer query (not just facet filters) — this facet's own count must
     *   ignore its own filter so the user can see what deselecting it would do, while a `global`
     *   aggregation with no filter would also ignore the base search query itself, hence the manual
     *   `filter` clone re-adding everything except this one facet.
     *
     * @param string $facetName
     * @param \Elastica\Query\BoolQuery $baseBoolQuery The outer bool query BEFORE any facet filters were
     *   applied (same clone-then-selectively-readd approach as core's own `getGlobalAggregationFilters()`).
     * @param array<\Elastica\Query\AbstractQuery> $otherNonVariantFacetFilters Other currently-selected
     *   NON-variant facets' filter queries, to re-add to the base query clone (mirrors core's own rule).
     * @param array<string, array{scope: string, value: mixed}> $otherSelectedVariantSelections Currently
     *   selected variant-scoped facets, excluding `$facetName` itself.
     */
    public function buildSelectedFacetAggregation(
        string $facetName,
        BoolQuery $baseBoolQuery,
        array $otherNonVariantFacetFilters,
        array $otherSelectedVariantSelections,
    ): AbstractAggregation;

    /**
     * Specification:
     * - Range-facet counterpart of {@link buildUnselectedFacetAggregation()}: `nested(variant-facet) >
     *   filter(other selected variant facets, if any) > stats(nums.<facetName>)`. `stats` (not
     *   `terms`+`reverse_nested`) directly gives min/max across the matching CONCRETES, which is exactly
     *   the range-slider bounds — no root-doc counting needed for a range facet.
     *
     * @param string $facetName
     * @param array<string, array{scope: string, value: mixed}> $otherSelectedVariantSelections
     */
    public function buildUnselectedRangeAggregation(string $facetName, array $otherSelectedVariantSelections): AbstractAggregation;

    /**
     * Specification:
     * - Range-facet counterpart of {@link buildSelectedFacetAggregation()}.
     *
     * @param string $facetName
     * @param \Elastica\Query\BoolQuery $baseBoolQuery
     * @param array<\Elastica\Query\AbstractQuery> $otherNonVariantFacetFilters
     * @param array<string, array{scope: string, value: mixed}> $otherSelectedVariantSelections
     */
    public function buildSelectedRangeAggregation(
        string $facetName,
        BoolQuery $baseBoolQuery,
        array $otherNonVariantFacetFilters,
        array $otherSelectedVariantSelections,
    ): AbstractAggregation;

    /**
     * The five `get*AggregationName()` methods below are the other half of the builder/extractor
     * contract alongside {@see AGGREGATION_GLOBAL_PREFIX}: `VariantFacetExtractor`/`VariantRangeExtractor`
     * call these with the SAME facet name to reconstruct the exact keys the aggregations above were built
     * under, so they can navigate back into the raw ES response and find the right nested/filter/terms
     * result. Real, load-bearing dependencies, not implementation detail — hence declared here.
     *
     * @param string $facetName
     */
    public function getNestedAggregationName(string $facetName): string;

    /**
     * @param string $facetName
     */
    public function getInnerFilterAggregationName(string $facetName): string;

    /**
     * @param string $facetName
     */
    public function getRootFilterAggregationName(string $facetName): string;

    /**
     * @param string $facetName
     */
    public function getValuesAggregationName(string $facetName): string;

    /**
     * @param string $facetName
     */
    public function getRootAggregationName(string $facetName): string;
}
