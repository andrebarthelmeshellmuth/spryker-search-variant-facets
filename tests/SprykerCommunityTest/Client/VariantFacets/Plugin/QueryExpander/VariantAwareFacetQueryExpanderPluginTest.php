<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\VariantFacets\Plugin\QueryExpander;

use Codeception\Test\Unit;
use Elastica\Query\Term;
use Generated\Shared\Transfer\FacetConfigTransfer;
use ReflectionMethod;
use Spryker\Client\SearchElasticsearch\AggregationExtractor\FacetValueTransformerFactoryInterface;
use Spryker\Client\SearchElasticsearch\Config\FacetConfig;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchFactory;
use Spryker\Client\SearchExtension\Dependency\Plugin\FacetSearchResultValueTransformerPluginInterface;
use SprykerCommunity\Client\VariantFacets\FacetScope\FacetScopeResolverInterface;
use SprykerCommunity\Client\VariantFacets\Plugin\QueryExpander\VariantAwareFacetQueryExpanderPlugin;
use SprykerCommunity\Client\VariantFacets\VariantFacetsFactory;

/**
 * Covers `normalizeRangeValue()`, `getOtherNonVariantFacetFilters()`, and `resolveTransformedFilterValue()`
 * in isolation — all three are either pure or take a narrow, easily-substituted dependency
 * (`FacetScopeResolverInterface`/`FacetValueTransformerFactoryInterface`, both single-method interfaces)
 * rather than needing the real ES-backed query-building chain. The plugin's actual orchestration methods
 * (`expandQuery()`, `addFacetFiltersToBoolQueryVariantAware()`, `addFacetAggregationToQuery()`) still need
 * the real `SearchElasticsearchFactory`/`FacetConfigInterface` chain to exercise meaningfully and stay
 * covered end to end instead by the WebDriver Presentation suite
 * (`tests/SprykerCommunityTest/Yves/VariantFacetsPresentation`), which proves them against a real
 * OpenSearch index rather than a web of mocks that could silently drift from real behavior.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group VariantFacets
 * @group Plugin
 * @group QueryExpander
 * @group VariantAwareFacetQueryExpanderPluginTest
 * Add your own group annotations below this line
 * @group Portable
 */
class VariantAwareFacetQueryExpanderPluginTest extends Unit
{
    public function testNormalizeRangeValueReadsMinAndMaxFromAnArray(): void
    {
        // Arrange & Act
        $result = $this->invokeNormalizeRangeValue(['min' => '10', 'max' => '21']);

        // Assert
        $this->assertSame(['min' => 10.0, 'max' => 21.0], $result);
    }

    public function testNormalizeRangeValueTreatsAnEmptyStringBoundAsUnset(): void
    {
        // Arrange & Act
        $result = $this->invokeNormalizeRangeValue(['min' => '', 'max' => '21']);

        // Assert
        $this->assertNull($result['min']);
        $this->assertSame(21.0, $result['max']);
    }

    public function testNormalizeRangeValueParsesAMinMaxStringForm(): void
    {
        // Arrange & Act
        $result = $this->invokeNormalizeRangeValue('10-21');

        // Assert
        $this->assertSame(['min' => 10.0, 'max' => 21.0], $result);
    }

    public function testNormalizeRangeValueParsesAMinOnlyStringForm(): void
    {
        // Arrange & Act
        $result = $this->invokeNormalizeRangeValue('10-');

        // Assert
        $this->assertSame(10.0, $result['min']);
        $this->assertNull($result['max']);
    }

    public function testNormalizeRangeValueParsesAMaxOnlyStringForm(): void
    {
        // Arrange & Act
        $result = $this->invokeNormalizeRangeValue('-21');

        // Assert
        $this->assertNull($result['min']);
        $this->assertSame(21.0, $result['max']);
    }

    public function testGetOtherNonVariantFacetFiltersExcludesTheGivenNameAndAnyVariantScopedFacet(): void
    {
        // Arrange
        $facetConfig = new FacetConfig();
        $facetConfig->addFacet($this->buildFacetConfigTransfer('color'));
        $facetConfig->addFacet($this->buildFacetConfigTransfer('size'));
        $facetConfig->addFacet($this->buildFacetConfigTransfer('brand'));

        $colorFilter = new Term(['color' => 'red']);
        $sizeFilter = new Term(['size' => '40']);
        $brandFilter = new Term(['brand' => 'acme']);
        $facetFilters = ['color' => $colorFilter, 'size' => $sizeFilter, 'brand' => $brandFilter];

        $facetScopeResolverMock = $this->createMock(FacetScopeResolverInterface::class);
        $facetScopeResolverMock->method('resolveScope')->willReturnMap([
            ['color', null],
            ['size', 'vals'],
            ['brand', null],
        ]);

        $plugin = $this->buildPluginWithVariantFacetsFactory($facetScopeResolverMock);

        // Act
        $result = $this->invokeGetOtherNonVariantFacetFilters($plugin, $facetConfig, $facetFilters, ['color' => 'red', 'size' => '40', 'brand' => 'acme'], 'brand');

        // Assert — brand excluded by name, size excluded as variant-scoped, color is the only survivor
        $this->assertSame([$colorFilter], $result);
    }

    public function testResolveTransformedFilterValueReturnsNullForAnEmptyValue(): void
    {
        // Arrange
        $plugin = $this->buildPluginWithSearchElasticsearchFactory($this->createMock(FacetValueTransformerFactoryInterface::class));

        // Act & Assert
        $this->assertNull($this->invokeResolveTransformedFilterValue($plugin, $this->buildFacetConfigTransfer('color'), []));
    }

    public function testResolveTransformedFilterValueReturnsTheRawValueWhenNoTransformerIsConfigured(): void
    {
        // Arrange
        $facetValueTransformerFactoryMock = $this->createMock(FacetValueTransformerFactoryInterface::class);
        $facetValueTransformerFactoryMock->method('createTransformer')->willReturn(null);

        $plugin = $this->buildPluginWithSearchElasticsearchFactory($facetValueTransformerFactoryMock);

        // Act
        $result = $this->invokeResolveTransformedFilterValue($plugin, $this->buildFacetConfigTransfer('color'), ['color' => 'red']);

        // Assert
        $this->assertSame('red', $result);
    }

    public function testResolveTransformedFilterValueUsesTheConfiguredTransformerWhenPresent(): void
    {
        // Arrange
        $transformerPluginMock = $this->createMock(FacetSearchResultValueTransformerPluginInterface::class);
        $transformerPluginMock->method('transformFromDisplay')->with('red')->willReturn('ROT');

        $facetValueTransformerFactoryMock = $this->createMock(FacetValueTransformerFactoryInterface::class);
        $facetValueTransformerFactoryMock->method('createTransformer')->willReturn($transformerPluginMock);

        $plugin = $this->buildPluginWithSearchElasticsearchFactory($facetValueTransformerFactoryMock);

        // Act
        $result = $this->invokeResolveTransformedFilterValue($plugin, $this->buildFacetConfigTransfer('color'), ['color' => 'red']);

        // Assert
        $this->assertSame('ROT', $result);
    }

    /**
     * @param array<string, mixed>|string $value
     *
     * @return array{min: float|null, max: float|null}
     */
    protected function invokeNormalizeRangeValue($value): array
    {
        $plugin = new VariantAwareFacetQueryExpanderPlugin();
        $method = new ReflectionMethod($plugin, 'normalizeRangeValue');

        /** @var array{min: float|null, max: float|null} $result */
        $result = $method->invoke($plugin, $value);

        return $result;
    }

    /**
     * @param array<string, \Elastica\Query\AbstractQuery> $facetFilters
     * @param array<string, mixed> $requestParameters
     *
     * @return array<\Elastica\Query\AbstractQuery>
     */
    protected function invokeGetOtherNonVariantFacetFilters(
        VariantAwareFacetQueryExpanderPlugin $plugin,
        FacetConfig $facetConfig,
        array $facetFilters,
        array $requestParameters,
        string $excludeFacetName,
    ): array {
        $method = new ReflectionMethod($plugin, 'getOtherNonVariantFacetFilters');

        /** @var array<\Elastica\Query\AbstractQuery> $result */
        $result = $method->invoke($plugin, $facetConfig, $facetFilters, $requestParameters, $excludeFacetName);

        return $result;
    }

    /**
     * @param array<string, mixed> $requestParameters
     *
     * @return mixed
     */
    protected function invokeResolveTransformedFilterValue(
        VariantAwareFacetQueryExpanderPlugin $plugin,
        FacetConfigTransfer $facetConfigTransfer,
        array $requestParameters,
    ) {
        $method = new ReflectionMethod($plugin, 'resolveTransformedFilterValue');

        return $method->invoke($plugin, $facetConfigTransfer, $requestParameters);
    }

    protected function buildFacetConfigTransfer(string $name): FacetConfigTransfer
    {
        return (new FacetConfigTransfer())
            ->setName($name)
            ->setParameterName($name)
            ->setFieldName($name)
            ->setType('term');
    }

    protected function buildPluginWithVariantFacetsFactory(FacetScopeResolverInterface $facetScopeResolverMock): VariantAwareFacetQueryExpanderPlugin
    {
        $variantFacetsFactoryMock = $this->createMock(VariantFacetsFactory::class);
        $variantFacetsFactoryMock->method('createFacetScopeResolver')->willReturn($facetScopeResolverMock);

        return new class ($variantFacetsFactoryMock) extends VariantAwareFacetQueryExpanderPlugin {
            public function __construct(protected VariantFacetsFactory $injectedVariantFacetsFactory)
            {
            }

            protected function getVariantFacetsFactory(): VariantFacetsFactory
            {
                return $this->injectedVariantFacetsFactory;
            }
        };
    }

    protected function buildPluginWithSearchElasticsearchFactory(
        FacetValueTransformerFactoryInterface $facetValueTransformerFactoryMock,
    ): VariantAwareFacetQueryExpanderPlugin {
        $searchElasticsearchFactoryMock = $this->createMock(SearchElasticsearchFactory::class);
        $searchElasticsearchFactoryMock->method('createFacetValueTransformerFactory')->willReturn($facetValueTransformerFactoryMock);

        return new class ($searchElasticsearchFactoryMock) extends VariantAwareFacetQueryExpanderPlugin {
            public function __construct(protected SearchElasticsearchFactory $injectedSearchElasticsearchFactory)
            {
            }

            protected function getSearchElasticsearchFactory(): SearchElasticsearchFactory
            {
                return $this->injectedSearchElasticsearchFactory;
            }
        };
    }
}
