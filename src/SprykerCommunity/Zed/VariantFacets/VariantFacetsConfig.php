<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets;

use Spryker\Zed\Kernel\AbstractBundleConfig;

/**
 * Empty by design, not dead weight: `getConfig()` (via `BundleConfigResolverAwareTrait`, available on
 * every Communication/Business class built on Spryker's kernel) resolves this class BY REFLECTION on
 * the calling object's own namespace, not via DI registration — if this file didn't exist, calling
 * `getConfig()` anywhere in this module would throw a hard "class not found" fatal, even from inherited
 * base-class boilerplate that never runs this module's own code. It also stays the required override
 * seam: a project can subclass it as `Pyz\Zed\VariantFacets\VariantFacetsConfig` later without this
 * package needing a release first. Add a toggle here once this package actually needs one.
 */
class VariantFacetsConfig extends AbstractBundleConfig
{
}
