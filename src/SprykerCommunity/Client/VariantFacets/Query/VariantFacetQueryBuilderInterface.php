<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\Query;

use Elastica\Query\BoolQuery;
use Elastica\Query\Nested;

interface VariantFacetQueryBuilderInterface
{
    /**
     * Specification:
     * - Builds ONE `nested` query (path `variant-facet`) whose inner bool.filter carries a clause per
     *   entry in `$selections` — this is what makes selecting values from different variant-scoped
     *   facets require a SINGLE concrete to satisfy all of them, instead of core's default of any concrete
     *   satisfying each facet independently.
     * - A multi-select facet's array of values becomes one `terms` clause (any of them, on the same
     *   concrete as every other selected facet) — OR within the facet, AND across facets, all inside the
     *   one nested clause.
     * - A range-scoped (`'nums'`) entry with a `min`/`max` becomes a `range` clause on `nums.<name>`,
     *   same AND-with-everything-else placement inside the shared nested clause.
     * - Returns null if `$selections` contains nothing buildable.
     *
     * @param array<string, array{scope: string, value: mixed}> $selections Facet name => resolved scope
     *   ('vals'|'nums') + the already-value-transformed filter value (string/array of strings for 'vals',
     *   array{min, max} for 'nums').
     */
    public function build(array $selections): ?Nested;

    /**
     * Same clauses as {@link build()} but without the `nested` wrapper — for use inside an aggregation
     * already scoped to the `variant-facet` nested path.
     *
     * @param array<string, array{scope: string, value: mixed}> $selections
     */
    public function buildInnerBoolQuery(array $selections): ?BoolQuery;
}
