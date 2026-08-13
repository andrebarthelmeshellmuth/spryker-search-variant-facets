<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\VariantFacetsPresentation\Presentation;

use SprykerCommunityTest\Yves\VariantFacetsPresentation\PageObject\SearchResultsPage;
use SprykerCommunityTest\Yves\VariantFacetsPresentation\VariantFacetsPresentationTester;

/**
 * P6's range-facet path (VariantFacetQueryBuilder::createNumsClause() / VariantRangeExtractor), driven
 * live rather than only at the unit level — closes the "no full CSV-fixture E2E was possible" gap this
 * package's own README used to flag for range facets before HP-ECO-45K's leadtime_days fixture existed.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group VariantFacetsPresentation
 * @group Presentation
 * @group RangeFacetCest
 * Add your own group annotations below this line
 */
class RangeFacetCest
{
    /**
     * @param \SprykerCommunityTest\Yves\VariantFacetsPresentation\VariantFacetsPresentationTester $i
     */
    public function _before(VariantFacetsPresentationTester $i): void
    {
        $i->amYves();
    }

    /**
     * @param \SprykerCommunityTest\Yves\VariantFacetsPresentation\VariantFacetsPresentationTester $i
     */
    public function rangeFacetRendersWithRealMinAndMax(VariantFacetsPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_HP_ECO_45K);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_ITEMS_FOUND_COUNT, 10);
        $i->see('Lead Time (days)');
    }

    /**
     * HP-ECO-45K's leadtime_days values are 10/14/21 (concretes -1..-3). A 25-30 window sits above all
     * three, so a correctly working range facet must exclude every concrete and return 0 — a broken
     * range clause (e.g. min/max swapped, or the facet silently not filtering at all) would instead
     * keep showing 1.
     *
     * @param \SprykerCommunityTest\Yves\VariantFacetsPresentation\VariantFacetsPresentationTester $i
     */
    public function outOfRangeSelectionExcludesTheAbstract(VariantFacetsPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_HP_ECO_45K_LEADTIME_OUT_OF_RANGE);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_ITEMS_FOUND_COUNT, 10);
        $i->assertSame(0, $i->grabItemsFoundCount());
    }
}
