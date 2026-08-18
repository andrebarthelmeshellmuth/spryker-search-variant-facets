<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\VariantFacets\UsefulnessFilter;

use ArrayObject;
use Codeception\Test\Unit;
use Generated\Shared\Transfer\FacetSearchResultTransfer;
use Generated\Shared\Transfer\FacetSearchResultValueTransfer;
use Generated\Shared\Transfer\RangeSearchResultTransfer;
use SprykerCommunity\Client\VariantFacets\UsefulnessFilter\VariantFacetUsefulnessFilter;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group VariantFacets
 * @group UsefulnessFilter
 * @group VariantFacetUsefulnessFilterTest
 * Add your own group annotations below this line
 * @group Portable
 */
class VariantFacetUsefulnessFilterTest extends Unit
{
    public function testBucketedFacetIsUsefulWhenAValueWouldNarrowTheResult(): void
    {
        // Arrange
        $filter = new VariantFacetUsefulnessFilter();
        $facet = (new FacetSearchResultTransfer())->setValues($this->buildValues(['Item' => 3, '5-pack' => 5]));

        // Act & Assert
        $this->assertTrue($filter->isBucketedFacetUseful($facet, 5), 'Item (3) is below the total (5) — selecting it would remove products.');
    }

    public function testBucketedFacetIsNotUsefulWhenEveryValueEqualsTheTotal(): void
    {
        // Arrange
        $filter = new VariantFacetUsefulnessFilter();
        $facet = (new FacetSearchResultTransfer())->setValues($this->buildValues(['Item' => 5, '5-pack' => 5]));

        // Act & Assert
        $this->assertFalse($filter->isBucketedFacetUseful($facet, 5), 'Every remaining product already has every value — nothing to narrow.');
    }

    public function testBucketedFacetIsAlwaysUsefulWhenCurrentlySelected(): void
    {
        // Arrange: even though every value equals the total (nothing left to narrow), the user must
        // still be able to see and deselect their own active choice.
        $filter = new VariantFacetUsefulnessFilter();
        $facet = (new FacetSearchResultTransfer())
            ->setValues($this->buildValues(['Item' => 5]))
            ->setActiveValue('Item');

        // Act & Assert
        $this->assertTrue($filter->isBucketedFacetUseful($facet, 5));
    }

    public function testRangeFacetIsUsefulWhenItHasARealSpan(): void
    {
        // Arrange
        $filter = new VariantFacetUsefulnessFilter();
        $range = (new RangeSearchResultTransfer())->setMin(40)->setMax(50);

        // Act & Assert
        $this->assertTrue($filter->isRangeFacetUseful($range));
    }

    public function testRangeFacetIsNotUsefulWhenCollapsedToASingleValue(): void
    {
        // Arrange
        $filter = new VariantFacetUsefulnessFilter();
        $range = (new RangeSearchResultTransfer())->setMin(45)->setMax(45);

        // Act & Assert
        $this->assertFalse($filter->isRangeFacetUseful($range), 'min === max means there is nothing to drag the slider by.');
    }

    /**
     * @param array<string, int> $docCountByValue
     *
     * @return \ArrayObject<int, \Generated\Shared\Transfer\FacetSearchResultValueTransfer>
     */
    protected function buildValues(array $docCountByValue): ArrayObject
    {
        $values = new ArrayObject();

        foreach ($docCountByValue as $value => $docCount) {
            $values->append((new FacetSearchResultValueTransfer())->setValue($value)->setDocCount($docCount));
        }

        return $values;
    }
}
