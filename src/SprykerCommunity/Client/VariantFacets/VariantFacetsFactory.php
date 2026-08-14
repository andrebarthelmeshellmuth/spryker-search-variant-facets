<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\VariantFacets;

use Elastica\Client;
use Spryker\Client\Kernel\AbstractFactory;
use Spryker\Client\SearchElasticsearch\Dependency\Client\SearchElasticsearchToStoreClientBridge;
use Spryker\Client\SearchElasticsearch\Dependency\Client\SearchElasticsearchToStoreClientInterface;
use Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolver;
use Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Client\Store\StoreClientInterface;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use SprykerCommunity\Client\VariantFacets\Aggregation\VariantFacetAggregationBuilder;
use SprykerCommunity\Client\VariantFacets\Aggregation\VariantFacetAggregationBuilderInterface;
use SprykerCommunity\Client\VariantFacets\AggregationExtractor\VariantFacetExtractor;
use SprykerCommunity\Client\VariantFacets\AggregationExtractor\VariantFacetExtractorInterface;
use SprykerCommunity\Client\VariantFacets\AggregationExtractor\VariantRangeExtractor;
use SprykerCommunity\Client\VariantFacets\AggregationExtractor\VariantRangeExtractorInterface;
use SprykerCommunity\Client\VariantFacets\FacetScope\FacetScopeResolver;
use SprykerCommunity\Client\VariantFacets\FacetScope\FacetScopeResolverInterface;
use SprykerCommunity\Client\VariantFacets\FacetScope\IndexMappingFacetScopeStrategy;
use SprykerCommunity\Client\VariantFacets\Query\VariantFacetQueryBuilder;
use SprykerCommunity\Client\VariantFacets\Query\VariantFacetQueryBuilderInterface;
use SprykerCommunity\Client\VariantFacets\Schema\IndexMappingReader;
use SprykerCommunity\Client\VariantFacets\Schema\IndexMappingReaderInterface;
use SprykerCommunity\Client\VariantFacets\UsefulnessFilter\VariantFacetUsefulnessFilter;
use SprykerCommunity\Client\VariantFacets\UsefulnessFilter\VariantFacetUsefulnessFilterInterface;

/**
 * @method \SprykerCommunity\Client\VariantFacets\VariantFacetsConfig getConfig()
 */
class VariantFacetsFactory extends AbstractFactory
{
    public function createFacetScopeResolver(): FacetScopeResolverInterface
    {
        return new FacetScopeResolver(
            $this->createIndexMappingFacetScopeStrategy(),
            $this->getConfig(),
        );
    }

    public function createIndexMappingFacetScopeStrategy(): FacetScopeResolverInterface
    {
        return new IndexMappingFacetScopeStrategy($this->createIndexMappingReader());
    }

    public function createVariantFacetQueryBuilder(): VariantFacetQueryBuilderInterface
    {
        return new VariantFacetQueryBuilder($this->getConfig());
    }

    public function createVariantFacetAggregationBuilder(): VariantFacetAggregationBuilderInterface
    {
        return new VariantFacetAggregationBuilder(
            $this->createVariantFacetQueryBuilder(),
            $this->createSearchElasticsearchConfig()->getFacetValueAggregationSize(),
        );
    }

    public function createVariantFacetExtractor(): VariantFacetExtractorInterface
    {
        return new VariantFacetExtractor($this->createVariantFacetAggregationBuilder());
    }

    public function createVariantRangeExtractor(): VariantRangeExtractorInterface
    {
        return new VariantRangeExtractor($this->createVariantFacetAggregationBuilder());
    }

    public function createVariantFacetUsefulnessFilter(): VariantFacetUsefulnessFilterInterface
    {
        return new VariantFacetUsefulnessFilter();
    }

    public function createIndexMappingReader(): IndexMappingReaderInterface
    {
        return new IndexMappingReader(
            $this->getElasticaClient(),
            $this->createIndexNameResolver(),
            $this->getConfig(),
        );
    }

    /**
     * COMPOSITION over the core SearchElasticsearch module, deliberately — same reasoning and same
     * pattern as `SprykerCommunity\Client\SearchDebug\SearchDebugFactory::getElasticaClient()`: this
     * module does not extend/override `Pyz\Client\SearchElasticsearch`, so the host project's right to do
     * so stays untouched. `ElasticaClientFactory` static-caches the client, so this shares the exact
     * connection the shop's own search uses.
     */
    public function getElasticaClient(): Client
    {
        return $this->createElasticaClientFactory()->createClient(
            $this->createSearchElasticsearchConfig()->getClientConfig(),
        );
    }

    public function createElasticaClientFactory(): ElasticaClientFactory
    {
        return new ElasticaClientFactory();
    }

    public function createSearchElasticsearchConfig(): SearchElasticsearchConfig
    {
        return new SearchElasticsearchConfig();
    }

    public function createIndexNameResolver(): IndexNameResolverInterface
    {
        return new IndexNameResolver(
            $this->createSearchElasticsearchToStoreClientBridge(),
            $this->createSearchElasticsearchConfig(),
        );
    }

    public function createSearchElasticsearchToStoreClientBridge(): SearchElasticsearchToStoreClientInterface
    {
        return new SearchElasticsearchToStoreClientBridge($this->getStoreClient());
    }

    public function getStoreClient(): StoreClientInterface
    {
        return $this->getProvidedDependency(VariantFacetsDependencyProvider::CLIENT_STORE);
    }
}
