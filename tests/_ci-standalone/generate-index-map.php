<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

/*
 * Standalone PageIndexMap generation -- mirrors generate-transfers.php's own direct-instantiation bypass.
 * Regenerates Generated\Shared\Search\PageIndexMap from this package's real dependency
 * (spryker/search-elasticsearch)'s own default `page` mapping, merged with this package's own additive
 * Schema/page.json fragment -- into src/Generated/, gitignored exactly like transfer output already is,
 * never committed. Replaces a copy of Spryker's own generated (and Spryker-copyrighted) output that used
 * to be checked into tests/_ci-standalone/Generated/ by mistake.
 */

declare(strict_types = 1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Spryker\Zed\SearchElasticsearch\Business\Installer\IndexMap\Generator\IndexMapGenerator;
use Spryker\Zed\SearchElasticsearch\SearchElasticsearchConfig;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$config = new SearchElasticsearchConfig();

$coreSchema = json_decode(
    (string)file_get_contents(APPLICATION_VENDOR_DIR . '/spryker/search-elasticsearch/src/Spryker/Shared/SearchElasticsearch/Schema/page.json'),
    true,
);
$mergedProperties = $coreSchema['mappings']['page']['properties'];

$packageSchemaPath = APPLICATION_SOURCE_DIR . '/SprykerCommunity/Shared/VariantFacets/Schema/page.json';

if (is_file($packageSchemaPath)) {
    $packageSchema = json_decode((string)file_get_contents($packageSchemaPath), true);
    $mergedProperties = array_merge($mergedProperties, $packageSchema['mappings']['page']['properties']);
}

$indexDefinitionTransfer = (new \Generated\Shared\Transfer\IndexDefinitionTransfer())
    ->setIndexName('page')
    ->setMappings(['page' => ['properties' => $mergedProperties]]);

$twig = new Environment(new FilesystemLoader($config->getIndexMapClassTemplateDirectory()));

(new IndexMapGenerator($config, $twig))->generate($indexDefinitionTransfer);

echo 'Index map generated into ' . $config->getClassTargetDirectory() . "\n";
