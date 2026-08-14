<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\VariantFacetsPresentation\PageObject;

/**
 * URLs encode facet selections directly in the querystring (`name[]=value` per checkbox,
 * `name[min]`/`name[max]` for a range) — confirmed live against a real browser session rather than
 * guessed, since the "Filter" button submits via a JS click handler, not a plain form GET.
 *
 * All values below come from this package's own fixture — see fixtures/apply.php and the README's
 * "Testing and CI" section. Searchable STL-7010 concretes: -1 (90°C/Item), -2 (90°C/5-pack),
 * -5 (130°C/5-pack), -6 (130°C/Box). -3 (90°C/Box) and -4 (130°C/Item) are deliberately excluded from
 * search (is_searchable=0) — that gap is what makes the cross-facet-AND fix observable at all: core's
 * OR-across-concretes bug would show the abstract as matching -3's or -4's combination anyway, even
 * though neither excluded concrete can be found or bought.
 */
class SearchResultsPage
{
    /**
     * @var string
     */
    public const URL_STL_7010 = '/en/search?q=STL-7010';

    /**
     * 90°C + Box: no SEARCHABLE concrete carries this exact combination (only excluded -3 does).
     * Under core's OR-across-concretes bug this would incorrectly show 1 match; the fix must show 0.
     *
     * @var string
     */
    public const URL_STL_7010_90C_AND_BOX = '/en/search?q=STL-7010&limitrange%5B%5D=90%C2%B0C&packaging_unit%5B%5D=Box';

    /**
     * 90°C + 5-pack: concrete -2 genuinely carries this exact combination. Real positive control —
     * proves the fix doesn't just suppress everything, it still matches a real combination.
     *
     * @var string
     */
    public const URL_STL_7010_90C_AND_5PACK = '/en/search?q=STL-7010&limitrange%5B%5D=90%C2%B0C&packaging_unit%5B%5D=5-pack';

    /**
     * 130°C + Item: no SEARCHABLE concrete carries this exact combination (only excluded -4 does).
     * Same shape as the 90°C/Box case above, exercised via the OTHER pair of values.
     *
     * @var string
     */
    public const URL_STL_7010_130C_AND_ITEM = '/en/search?q=STL-7010&limitrange%5B%5D=130%C2%B0C&packaging_unit%5B%5D=Item';

    /**
     * @var string
     */
    public const URL_HP_ECO_45K = '/en/search?q=HP-ECO-45K';

    /**
     * HP-ECO-45K's leadtime_days values are 10/14/21 (concretes -1..-3) — a 25-30 window is above all
     * of them, so a correctly working range facet must exclude every concrete and return 0.
     *
     * @var string
     */
    public const URL_HP_ECO_45K_LEADTIME_OUT_OF_RANGE = '/en/search?q=HP-ECO-45K&leadtime_days%5Bmin%5D=25&leadtime_days%5Bmax%5D=30';

    /**
     * Confirmed live: `<div class="sort__col sort__col--counter col"><strong>N</strong> Items found</div>`.
     *
     * @var string
     */
    public const SELECTOR_ITEMS_FOUND_COUNT = '.sort__col--counter strong';
}
