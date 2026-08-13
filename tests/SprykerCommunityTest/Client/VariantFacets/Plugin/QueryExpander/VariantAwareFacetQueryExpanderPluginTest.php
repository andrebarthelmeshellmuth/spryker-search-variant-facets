<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\VariantFacets\Plugin\QueryExpander;

use Codeception\Test\Unit;
use ReflectionMethod;
use SprykerCommunity\Client\VariantFacets\Plugin\QueryExpander\VariantAwareFacetQueryExpanderPlugin;

/**
 * Covers `normalizeRangeValue()` in isolation — a pure parsing routine, no factory/DI chain needed. The
 * plugin's orchestration methods (`expandQuery()`, `addFacetFiltersToBoolQueryVariantAware()`,
 * `addFacetAggregationToQuery()`) need the real `SearchElasticsearchFactory`/`FacetConfigInterface` chain
 * to exercise meaningfully and are covered end to end instead by the WebDriver Presentation suite
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
}
