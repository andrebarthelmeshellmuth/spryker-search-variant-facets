<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\VariantFacets\Communication\Console;

use Elastica\Client;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\Config\Config;
use Spryker\Shared\Kernel\KernelConstants;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use Spryker\Zed\Kernel\Communication\Console\Console;
use SprykerCommunity\Client\VariantFacets\Plugin\Catalog\MatchingVariantResultFormatterPlugin;
use SprykerCommunity\Client\VariantFacets\Plugin\QueryExpander\VariantAwareFacetQueryExpanderPlugin;
use SprykerCommunity\Client\VariantFacets\Plugin\ResultFormatter\VariantAwareFacetResultFormatterPlugin;
use SprykerCommunity\Zed\VariantFacets\Communication\Plugin\ProductPageSearch\VariantAttributesPageDataExpanderPlugin;
use SprykerCommunity\Zed\VariantFacets\Communication\Plugin\ProductPageSearch\VariantFacetMapExpanderPlugin;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Diagnoses a search-variant-facets installation.
 *
 * Same failure shape as every sibling package: a forgotten DependencyProvider wire-up produces no error,
 * facets just keep matching OR-across-concretes as core always did. This checks every prerequisite
 * reachable from the CLI and names the exact remedy for whatever is wrong.
 *
 * Unlike the sibling packages, this one ships NO Yves-layer code of its own (see the README's "Why no
 * Yves widget" section — the matching-variant tile-swap payload is read by the project's own template,
 * not rendered by this package), so there is no complementary Yves check-installation page: every install
 * step this package has lives in the Zed and Client layers, both of which a Zed console CAN reach directly
 * — the Client-layer plugin classes are loadable and instantiable with zero HTTP/session dependency (they
 * are exactly what a project's own CatalogDependencyProvider `new`s up), and the OpenSearch mapping is
 * reachable the same raw-Elastica-bypass way {@see \SprykerCommunity\Zed\SearchDebug\Communication\Console\SearchDebugCheckInstallationConsole}
 * already uses (a real Client\SearchElasticsearch\SearchElasticsearchClient call from Zed crashes for lack
 * of an HTTP session — instantiating Elastica directly sidesteps that entirely).
 *
 * What this CANNOT check: whether the project's own `CatalogDependencyProvider` actually REPLACED core's
 * `FacetQueryExpanderPlugin`/`FacetResultFormatterPlugin` with this package's variant-aware versions (as
 * opposed to registering both, or neither) — that class lives in the adopting project's own `Pyz`-or-
 * equivalent namespace, which this package cannot reference without breaking portability to every other
 * adopter's namespace. The mapping check below is the closest indirect signal: the `variant-facet` nested
 * field only gets populated by a real export run through `VariantFacetMapExpanderPlugin`, so a populated
 * mapping is strong evidence the Zed-side plugin really is wired in.
 */
class VariantFacetsCheckInstallationConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-variant-facets:check-installation';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Diagnoses a search-variant-facets installation: core namespace, plugin classes, search engine reachability, and the variant-facet index mapping.';

    /**
     * @var string
     */
    protected const CORE_NAMESPACE = 'SprykerCommunity';

    /**
     * @var string
     */
    protected const PAGE_SOURCE_IDENTIFIER = 'page';

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
     * @var array<string>
     */
    protected array $failures = [];

    /**
     * @var array<string>
     */
    protected array $warnings = [];

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);

        parent::configure();
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter $input is mandated by the Console base class.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->checkCoreNamespace($output);
        $this->checkPluginClasses($output);
        $this->checkSearchEngine($output);

        $output->writeln('');

        foreach ($this->warnings as $warning) {
            $output->writeln(sprintf('<comment>! %s</comment>', $warning));
        }

        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                $output->writeln(sprintf('<error>✗ %s</error>', $failure));
            }

            return static::CODE_ERROR;
        }

        $output->writeln('<info>Everything checkable from the CLI is in place.</info>');
        $output->writeln('Not verifiable from here — confirm by hand in your own project:');
        $output->writeln('  - that Pyz\Client\Catalog\CatalogDependencyProvider (or your project\'s equivalent) actually');
        $output->writeln('    REPLACES core\'s FacetQueryExpanderPlugin/FacetResultFormatterPlugin with');
        $output->writeln('    VariantAwareFacetQueryExpanderPlugin/VariantAwareFacetResultFormatterPlugin, not adds alongside them');
        $output->writeln('  - that VariantFacetMapExpanderPlugin is registered LAST in your ProductPageSearchDependencyProvider\'s');
        $output->writeln('    getProductAbstractMapExpanderPlugins() — an earlier position silently drops the field it adds');
        $output->writeln('  - a populated "variant-facet" mapping (checked below when the index exists) is strong evidence both');
        $output->writeln('    of the above are already correct, since nothing else can produce that field');

        return static::CODE_SUCCESS;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkCoreNamespace(OutputInterface $output): void
    {
        $coreNamespaces = Config::get(KernelConstants::CORE_NAMESPACES, []);

        if (in_array(static::CORE_NAMESPACE, $coreNamespaces, true)) {
            $output->writeln(sprintf('<info>✓</info> core namespace "%s" is registered', static::CORE_NAMESPACE));

            return;
        }

        $this->failures[] = sprintf(
            'Core namespace "%s" is NOT registered. Add it to KernelConstants::CORE_NAMESPACES in config/Shared/config_default.php — without it Spryker cannot resolve any of this package\'s classes.',
            static::CORE_NAMESPACE,
        );
    }

    /**
     * Class existence AND instantiability — unlike the sibling packages, this package's own Client-layer
     * plugins are plain `new X()`-able with zero constructor dependencies (exactly how a project's own
     * CatalogDependencyProvider registers them), so this checks a real `new` rather than settling for
     * `class_exists()` alone.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkPluginClasses(OutputInterface $output): void
    {
        $requiredClasses = [
            'variant-aware facet query expander plugin' => VariantAwareFacetQueryExpanderPlugin::class,
            'variant-aware facet result formatter plugin' => VariantAwareFacetResultFormatterPlugin::class,
            'matching-variant result formatter plugin' => MatchingVariantResultFormatterPlugin::class,
            'variant attributes page data expander plugin' => VariantAttributesPageDataExpanderPlugin::class,
            'variant facet map expander plugin' => VariantFacetMapExpanderPlugin::class,
        ];

        foreach ($requiredClasses as $label => $className) {
            try {
                new $className();
            } catch (Throwable $exception) {
                $this->failures[] = sprintf('The %s (%s) could not be instantiated: %s', $label, $className, $exception->getMessage());

                continue;
            }

            $output->writeln(sprintf('<info>✓</info> %s is loadable and instantiable', $label));
        }
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkSearchEngine(OutputInterface $output): void
    {
        try {
            $searchElasticsearchConfig = new SearchElasticsearchConfig();
            $elasticaClient = (new ElasticaClientFactory())->createClient($searchElasticsearchConfig->getClientConfig());
            $info = $elasticaClient->request('')->getData();
        } catch (Throwable $exception) {
            $this->failures[] = sprintf('Search engine is not reachable: %s', $exception->getMessage());

            return;
        }

        $version = $info['version'] ?? [];
        $output->writeln(sprintf(
            '<info>✓</info> search engine reachable: %s %s (Lucene %s)',
            (string)($version['distribution'] ?? 'elasticsearch'),
            (string)($version['number'] ?? '?'),
            (string)($version['lucene_version'] ?? '?'),
        ));

        $this->checkPageIndex($elasticaClient, $searchElasticsearchConfig, $output);
    }

    /**
     * @param \Elastica\Client $elasticaClient
     * @param \Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig $searchElasticsearchConfig
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkPageIndex(Client $elasticaClient, SearchElasticsearchConfig $searchElasticsearchConfig, OutputInterface $output): void
    {
        $indexPrefix = $searchElasticsearchConfig->getIndexPrefix();

        try {
            $aliases = $elasticaClient->request('_aliases')->getData();
        } catch (Throwable $exception) {
            $this->warnings[] = sprintf('Could not list indexes (%s) — skipping the variant-facet mapping check.', $exception->getMessage());

            return;
        }

        $pageIndexes = [];

        foreach (array_keys($aliases) as $indexName) {
            if (!str_starts_with((string)$indexName, $indexPrefix) || !str_ends_with((string)$indexName, static::PAGE_SOURCE_IDENTIFIER)) {
                continue;
            }

            $pageIndexes[] = (string)$indexName;
        }

        if ($pageIndexes === []) {
            $this->warnings[] = sprintf(
                'No "%s*...%s" index found yet, so the variant-facet mapping could not be checked. Run the publish/sync pipeline once, then re-run this command.',
                $indexPrefix,
                static::PAGE_SOURCE_IDENTIFIER,
            );

            return;
        }

        $output->writeln(sprintf('<info>✓</info> page index found: %s', implode(', ', $pageIndexes)));

        $this->checkVariantFacetMapping($elasticaClient, $pageIndexes[0], $output);
    }

    /**
     * Confirms both that the nested `variant-facet` field exists at all (proof
     * `VariantFacetMapExpanderPlugin` ran during export) AND that `vals`/`nums` are still shaped as
     * `{"dynamic": true}` plain objects, not `dynamic_templates` entries — the latter is exactly the
     * mapping-merge corruption this package's own README documents (`array_replace_recursive()` mangling a
     * second package's `dynamic_templates` array into core's). A corrupted mapping fails index creation
     * outright, so seeing ANY document in the index with this field already rules that out; this asserts
     * it explicitly anyway, since a future manual mapping edit could reintroduce it.
     *
     * @param \Elastica\Client $elasticaClient
     * @param string $indexName
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkVariantFacetMapping(Client $elasticaClient, string $indexName, OutputInterface $output): void
    {
        try {
            $mapping = $elasticaClient->getIndex($indexName)->getMapping();
        } catch (Throwable $exception) {
            $this->failures[] = sprintf('Could not read the mapping for "%s": %s', $indexName, $exception->getMessage());

            return;
        }

        $variantFacetProperties = $mapping['properties'][static::FIELD_VARIANT_FACET]['properties'] ?? null;

        if ($variantFacetProperties === null) {
            $this->warnings[] = sprintf(
                'Index "%s" has no "%s" field yet. This is normal before the first export that touches a product using a variant-scoped facet — nothing to fix unless you expect one to exist already.',
                $indexName,
                static::FIELD_VARIANT_FACET,
            );

            return;
        }

        foreach ([static::FIELD_VALS, static::FIELD_NUMS] as $subField) {
            $subFieldMapping = $variantFacetProperties[$subField] ?? null;

            if ($subFieldMapping === null) {
                continue;
            }

            // OpenSearch/Elasticsearch normalize a PUT-time JSON boolean `"dynamic": true` to the STRING
            // "true" in mapping read responses — confirmed empirically against a live index — so this
            // compares loosely against both representations rather than a strict `!== true`.
            $dynamicValue = $subFieldMapping['dynamic'] ?? null;

            if (!in_array($dynamicValue, [true, 'true'], true) || isset($subFieldMapping['properties']['dynamic_templates'])) {
                $this->failures[] = sprintf(
                    'Index "%s" field "%s.%s" is not shaped as a plain {"dynamic": true} object — this is the exact dynamic_templates merge corruption this package\'s README warns about. Reindex from Shared/VariantFacets/Schema/page.json as shipped, without hand-editing the mapping.',
                    $indexName,
                    static::FIELD_VARIANT_FACET,
                    $subField,
                );

                return;
            }
        }

        $output->writeln(sprintf('<info>✓</info> "%s" mapping is present and correctly shaped', static::FIELD_VARIANT_FACET));
    }
}
