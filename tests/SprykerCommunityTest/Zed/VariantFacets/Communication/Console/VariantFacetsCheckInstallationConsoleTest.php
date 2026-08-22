<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\VariantFacets\Communication\Console;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\VariantFacets\Communication\Console\VariantFacetsCheckInstallationConsole;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Every check here — core namespace, plugin instantiability, search-engine reachability, page-index
 * shape, the variant-facet mapping — deliberately hits this demoshop's OWN real installation rather than
 * a mock: this command exists specifically to diagnose a REAL installation, and it constructs its own
 * Elastica client directly rather than going through any injectable Facade/Factory/Locator seam, so there
 * is nothing to substitute even if a mock were desirable. This demoshop is expected to be fully wired
 * (core namespace registered, every plugin class instantiable, a real reachable search engine with an
 * exported page index carrying a correctly-shaped "variant-facet" mapping) — asserted on accordingly,
 * same portability tradeoff every sibling package's own CheckInstallationConsoleTest already accepts.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group VariantFacets
 * @group Communication
 * @group Console
 * @group VariantFacetsCheckInstallationConsoleTest
 * @group NeedsProject
 */
class VariantFacetsCheckInstallationConsoleTest extends Unit
{
    public function testSucceedsAndReportsEveryCheckAgainstTheRealInstallation(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester();

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(VariantFacetsCheckInstallationConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('core namespace "SprykerCommunity" is registered', $commandTester->getDisplay());
        $this->assertStringContainsString('variant-aware facet query expander plugin is loadable and instantiable', $commandTester->getDisplay());
        $this->assertStringContainsString('variant-aware facet result formatter plugin is loadable and instantiable', $commandTester->getDisplay());
        $this->assertStringContainsString('matching-variant result formatter plugin is loadable and instantiable', $commandTester->getDisplay());
        $this->assertStringContainsString('variant attributes page data expander plugin is loadable and instantiable', $commandTester->getDisplay());
        $this->assertStringContainsString('variant facet map expander plugin is loadable and instantiable', $commandTester->getDisplay());
        $this->assertStringContainsString('search engine reachable', $commandTester->getDisplay());
        $this->assertStringContainsString('Everything checkable from the CLI is in place.', $commandTester->getDisplay());
    }

    protected function createCommandTester(): CommandTester
    {
        $console = new VariantFacetsCheckInstallationConsole();

        $application = new Application();
        $application->add($console);

        $command = $application->find(VariantFacetsCheckInstallationConsole::COMMAND_NAME);

        return new CommandTester($command);
    }
}
