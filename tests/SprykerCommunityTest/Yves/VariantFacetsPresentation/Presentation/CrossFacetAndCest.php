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
 * The package's headline scenario, driven end to end through a real browser against the real storefront
 * — not just the unit-level query-builder assertions the Client/Zed suites already cover. Every count
 * here was confirmed live before being hardcoded (see SearchResultsPage's own docblocks for the exact
 * concrete each URL depends on).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group VariantFacetsPresentation
 * @group Presentation
 * @group CrossFacetAndCest
 * Add your own group annotations below this line
 */
class CrossFacetAndCest
{
    /**
     * @param \SprykerCommunityTest\Yves\VariantFacetsPresentation\VariantFacetsPresentationTester $i
     */
    public function _before(VariantFacetsPresentationTester $i): void
    {
        $i->amYves();
    }

    /**
     * The core bug this package fixes: core's OR-across-concretes indexing would match the STL-7010
     * abstract on this combination too (90°C comes from concretes -1/-2, Box comes from concrete -6),
     * even though no single SEARCHABLE concrete carries both at once — only the excluded -3 does.
     *
     * @param \SprykerCommunityTest\Yves\VariantFacetsPresentation\VariantFacetsPresentationTester $i
     */
    public function unmatchedCrossFacetCombinationExcludesTheAbstract(VariantFacetsPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_STL_7010_90C_AND_BOX);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_ITEMS_FOUND_COUNT, 10);
        $i->assertSame(0, $i->grabItemsFoundCount());
    }

    /**
     * The positive control: concrete -2 genuinely carries 90°C + 5-pack together, so the fix must still
     * match it — this isn't just a filter that suppresses everything.
     *
     * @param \SprykerCommunityTest\Yves\VariantFacetsPresentation\VariantFacetsPresentationTester $i
     */
    public function realCrossFacetCombinationStillMatches(VariantFacetsPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_STL_7010_90C_AND_5PACK);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_ITEMS_FOUND_COUNT, 10);
        $i->assertSame(1, $i->grabItemsFoundCount());
    }

    /**
     * Same shape as the 90°C/Box case, exercised via the OTHER excluded combination (130°C + Item,
     * which only excluded concrete -4 carries) — confirms the fix isn't accidentally specific to one
     * facet pairing.
     *
     * @param \SprykerCommunityTest\Yves\VariantFacetsPresentation\VariantFacetsPresentationTester $i
     */
    public function secondUnmatchedCrossFacetCombinationExcludesTheAbstract(VariantFacetsPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_STL_7010_130C_AND_ITEM);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_ITEMS_FOUND_COUNT, 10);
        $i->assertSame(0, $i->grabItemsFoundCount());
    }
}
