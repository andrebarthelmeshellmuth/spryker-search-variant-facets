<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\Query;

use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\InnerHits;
use Elastica\Query\Nested;
use Elastica\Query\Range;
use Elastica\Query\Term;
use Elastica\Query\Terms;
use SprykerCommunity\Client\VariantFacets\FacetScope\IndexMappingFacetScopeStrategy;
use SprykerCommunity\Client\VariantFacets\VariantFacetsConfig;

class VariantFacetQueryBuilder implements VariantFacetQueryBuilderInterface
{
    /**
     * @var string
     */
    protected const PATH_VARIANT_FACET = 'variant-facet';

    /**
     * Also the `inner_hits` name — `MatchingVariantResultFormatterPlugin` reads
     * `Elastica\Result::getInnerHits()[self::PATH_VARIANT_FACET]` by this same constant.
     *
     * @var int
     */
    protected const INNER_HITS_SIZE = 10;

    public function __construct(protected VariantFacetsConfig $config)
    {
    }

    /**
     * Elasticsearch's default dynamic mapping types an unknown string sub-field as `text` with a
     * `.keyword` multi-field for exact matching — see spryker-page-json-dynamic-templates-merge-gotcha.
     * `vals.*` is always this shape; `nums.*` doesn't need it (dynamically typed `double`, per
     * VariantAttributeResolver always casting to float).
     *
     * @var string
     */
    protected const KEYWORD_SUFFIX = '.keyword';

    /**
     * @param array<string, array{scope: string, value: mixed}> $selections
     */
    public function build(array $selections): ?Nested
    {
        $boolQuery = $this->buildInnerBoolQuery($selections);

        if ($boolQuery === null) {
            return null;
        }

        $nested = new Nested();
        $nested->setPath(static::PATH_VARIANT_FACET);
        $nested->setQuery($boolQuery);

        if ($this->config->isMatchingVariantTileSwapEnabled()) {
            $innerHits = new InnerHits();
            $innerHits->setName(static::PATH_VARIANT_FACET);
            $innerHits->setSize(static::INNER_HITS_SIZE);
            $nested->setInnerHits($innerHits);
        }

        return $nested;
    }

    /**
     * Same clauses as {@link build()} but WITHOUT the `nested` wrapper — for use inside an aggregation
     * that is already scoped to the `variant-facet` nested path (a second `nested` wrapper there would be
     * invalid: aggregation-level nesting, not query-level).
     *
     * @param array<string, array{scope: string, value: mixed}> $selections
     */
    public function buildInnerBoolQuery(array $selections): ?BoolQuery
    {
        $boolQuery = new BoolQuery();
        $hasClause = false;

        foreach ($selections as $facetName => $selection) {
            if ($selection['scope'] === IndexMappingFacetScopeStrategy::SCOPE_VALS) {
                $boolQuery->addFilter($this->createValsClause($facetName, $selection['value']));
                $hasClause = true;

                continue;
            }

            if ($selection['scope'] !== IndexMappingFacetScopeStrategy::SCOPE_NUMS) {
                continue;
            }

            $rangeClause = $this->createNumsClause($facetName, $selection['value']);

            if ($rangeClause === null) {
                continue;
            }

            $boolQuery->addFilter($rangeClause);
            $hasClause = true;
        }

        return $hasClause ? $boolQuery : null;
    }

    /**
     * @param string $facetName
     * @param mixed $value
     */
    protected function createValsClause(string $facetName, $value): AbstractQuery
    {
        $fieldPath = sprintf('%s.vals.%s%s', static::PATH_VARIANT_FACET, $facetName, static::KEYWORD_SUFFIX);

        if (is_array($value)) {
            return new Terms($fieldPath, array_values($value));
        }

        return new Term([$fieldPath => $value]);
    }

    /**
     * @param string $facetName
     * @param array<string, float|null> $value Shape: `['min' => float|null, 'max' => float|null]`.
     */
    protected function createNumsClause(string $facetName, array $value): ?Range
    {
        $args = [];

        if ($value['min'] !== null) {
            $args['gte'] = $value['min'];
        }

        if ($value['max'] !== null) {
            $args['lte'] = $value['max'];
        }

        if ($args === []) {
            return null;
        }

        $fieldPath = sprintf('%s.nums.%s', static::PATH_VARIANT_FACET, $facetName);

        return new Range($fieldPath, $args);
    }
}
