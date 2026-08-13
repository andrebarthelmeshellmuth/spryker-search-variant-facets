<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\Plugin\Catalog;

use Elastica\ResultSet;
use Generated\Shared\Search\PageIndexMap;
use Spryker\Client\SearchElasticsearch\Plugin\ResultFormatter\AbstractElasticsearchResultFormatterPlugin;

/**
 * Reads back the `inner_hits` `VariantFacetQueryBuilder` requests when `VariantFacetsConfig::
 * isMatchingVariantTileSwapEnabled()` is on — same additive-payload approach as search-ranking's
 * `RandomImpactResultFormatterPlugin`: one extra keyed-by-`idProductAbstract` result-formatter entry a
 * storefront widget can look up, nothing forced into the product tile itself. Returns `[]` when the
 * feature is off (no `inner_hits` were requested, so there's nothing to read).
 *
 * @method \SprykerCommunity\Client\VariantFacets\VariantFacetsFactory getFactory()
 */
class MatchingVariantResultFormatterPlugin extends AbstractElasticsearchResultFormatterPlugin
{
    /**
     * @var string
     */
    public const NAME = 'matchingVariantSkus';

    /**
     * @var string
     */
    protected const INNER_HITS_NAME = 'variant-facet';

    /**
     * @var string
     */
    protected const PRODUCT_ID_KEY = 'id_product_abstract';

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return string
     */
    public function getName()
    {
        return static::NAME;
    }

    /**
     * @param \Elastica\ResultSet $searchResult
     * @param array<string, mixed> $requestParameters
     *
     * @return array<int, array<string>>
     */
    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter -- signature is fixed by AbstractElasticsearchResultFormatterPlugin.

    protected function formatSearchResult(ResultSet $searchResult, array $requestParameters): array
    {
        // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter
        if (!$this->getFactory()->getConfig()->isMatchingVariantTileSwapEnabled()) {
            return [];
        }

        $matchingSkusByIdProductAbstract = [];

        foreach ($searchResult->getResults() as $document) {
            $source = $document->getSource();
            $idProductAbstract = $source[PageIndexMap::SEARCH_RESULT_DATA][static::PRODUCT_ID_KEY] ?? null;

            if ($idProductAbstract === null || !$document->hasParam('inner_hits')) {
                continue;
            }

            $skus = $this->extractMatchingSkus($document->getInnerHits());

            if ($skus === []) {
                continue;
            }

            $matchingSkusByIdProductAbstract[(int)$idProductAbstract] = $skus;
        }

        return $matchingSkusByIdProductAbstract;
    }

    /**
     * @param array<string, mixed> $innerHits
     *
     * @return array<string>
     */
    protected function extractMatchingSkus(array $innerHits): array
    {
        $hits = $innerHits[static::INNER_HITS_NAME]['hits']['hits'] ?? [];
        $skus = [];

        foreach ($hits as $hit) {
            if (!isset($hit['_source']['sku'])) {
                continue;
            }

            $skus[] = (string)$hit['_source']['sku'];
        }

        return $skus;
    }
}
