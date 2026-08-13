<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets\Schema;

use Elastica\Client;
use Elastica\Exception\ExceptionInterface;
use Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface;
use SprykerCommunity\Client\VariantFacets\VariantFacetsConfig;

class IndexMappingReader implements IndexMappingReaderInterface
{
    /**
     * @var string
     */
    protected const FIELD_VARIANT_FACET = 'variant-facet';

    /**
     * @var string
     */
    protected const FIELD_VALS = 'vals';

    /**
     * @var string
     */
    protected const FIELD_NUMS = 'nums';

    /**
     * Memoized per index name for the lifetime of the PHP process — same reasoning as
     * `SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReader`: schema is stable within a request,
     * and facet-scope resolution happens once per active facet per search. Failed reads are NOT cached.
     *
     * @var array<string, array<string, array<string>>>
     */
    protected static array $variantScopedFacetNamesCache = [];

    /**
     * @param \Elastica\Client $elasticaClient
     * @param \Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface $indexNameResolver
     * @param \SprykerCommunity\Client\VariantFacets\VariantFacetsConfig $config
     */
    public function __construct(
        protected Client $elasticaClient,
        protected IndexNameResolverInterface $indexNameResolver,
        protected VariantFacetsConfig $config,
    ) {
    }

    /**
     * @return array<string>
     */
    public function getVariantScopedStringFacetNames(): array
    {
        return $this->getVariantScopedFacetNames()[static::FIELD_VALS];
    }

    /**
     * @return array<string>
     */
    public function getVariantScopedNumericFacetNames(): array
    {
        return $this->getVariantScopedFacetNames()[static::FIELD_NUMS];
    }

    /**
     * @return array<string, array<string>>
     */
    protected function getVariantScopedFacetNames(): array
    {
        $indexName = $this->indexNameResolver->resolve($this->config->getPageSourceIdentifier());

        if (isset(static::$variantScopedFacetNamesCache[$indexName])) {
            return static::$variantScopedFacetNamesCache[$indexName];
        }

        $result = [static::FIELD_VALS => [], static::FIELD_NUMS => []];

        try {
            $mapping = $this->elasticaClient->getIndex($indexName)->getMapping();
        } catch (ExceptionInterface) {
            // Fail-soft, uncached: every facet falls back to core's normal handling.
            return $result;
        }

        $variantFacetProperties = $mapping['properties'][static::FIELD_VARIANT_FACET]['properties'] ?? [];

        // array_keys() on a json_decode()'d mapping can yield int keys for all-digit facet names (PHP
        // casts numeric-string array keys to int) — cast back to string, the type this class promises.
        $result[static::FIELD_VALS] = $this->stringKeys($variantFacetProperties[static::FIELD_VALS]['properties'] ?? []);
        $result[static::FIELD_NUMS] = $this->stringKeys($variantFacetProperties[static::FIELD_NUMS]['properties'] ?? []);

        static::$variantScopedFacetNamesCache[$indexName] = $result;

        return $result;
    }

    /**
     * @param array<mixed> $array
     *
     * @return array<string>
     */
    protected function stringKeys(array $array): array
    {
        return array_map(fn (mixed $key): string => (string)$key, array_keys($array));
    }
}
