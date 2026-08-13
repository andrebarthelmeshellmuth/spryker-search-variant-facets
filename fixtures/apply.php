<?php

/**
 * This file is part of the spryker-community/search-variant-facets package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

/**
 * Applies this package's demo-fixture claims (see the toolkit repo's FIXTURE_CLAIMS.md) to a real
 * b2b-demo-marketplace checkout's shared import CSVs. Idempotent — safe to re-run; each change is
 * applied only if not already present, so running this against a checkout that already has some or all
 * of it (e.g. after a partial manual apply) does not duplicate rows or clobber unrelated edits.
 *
 * Deliberately edits by HEADER NAME, not column position or raw line matching — a full-file patch/diff
 * would silently fail or corrupt data the moment the demoshop's own CSVs shift upstream; this only
 * touches the exact fields it owns.
 *
 * Usage: php fixtures/apply.php /path/to/b2b-demo-marketplace
 *
 * Then, from that demoshop checkout:
 *   ./docker/sdk console data:import product-attribute-key # MUST run before product-search-attribute
 *   ./docker/sdk console data:import product-search-attribute
 *   ./docker/sdk console data:import product-concrete
 *   ./docker/sdk console publish:trigger-events -r product_abstract -i <id_product_abstract of STL-7010 and HP-ECO-45K>
 *   ./docker/sdk console queue:worker:start --stop-when-empty
 */

$demoshopRoot = $argv[1] ?? null;

if ($demoshopRoot === null || !is_dir($demoshopRoot)) {
    fwrite(STDERR, "Usage: php fixtures/apply.php /path/to/b2b-demo-marketplace\n");

    exit(1);
}

$dataDir = rtrim($demoshopRoot, '/') . '/data/import/common/common';

if (!is_dir($dataDir)) {
    fwrite(STDERR, "Not a b2b-demo-marketplace checkout (missing $dataDir)\n");

    exit(1);
}

/**
 * @param string $path
 *
 * @return array{header: array<int, string>, rows: array<int, array<string, string>>}
 */
function readCsv(string $path): array
{
    $handle = fopen($path, 'r');
    $header = fgetcsv($handle);
    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) !== count($header)) {
            continue;
        }

        $rows[] = array_combine($header, $row);
    }

    fclose($handle);

    return ['header' => $header, 'rows' => $rows];
}

/**
 * @param string $path
 * @param array<int, string> $header
 * @param array<int, array<string, string>> $rows
 */
function writeCsv(string $path, array $header, array $rows): void
{
    $handle = fopen($path, 'w');
    fputcsv($handle, $header);

    foreach ($rows as $row) {
        fputcsv($handle, array_map(fn (string $key) => $row[$key] ?? '', $header));
    }

    fclose($handle);
}

// --- 1. product_attribute_key.csv: register the 3 attribute keys this package's fixture uses ---

$path = $dataDir . '/product_attribute_key.csv';
$csv = readCsv($path);
$existingKeys = array_column($csv['rows'], 'attribute_key');
$newKeys = ['limitrange', 'packaging_unit', 'leadtime_days'];
$added = 0;

foreach ($newKeys as $key) {
    if (in_array($key, $existingKeys, true)) {
        continue;
    }

    $csv['rows'][] = ['attribute_key' => $key, 'is_super' => '1'];
    $added++;
}

if ($added > 0) {
    writeCsv($path, $csv['header'], $csv['rows']);
}

echo "product_attribute_key.csv: $added row(s) added\n";

// --- 2. product_search_attribute.csv: register limitrange/packaging_unit/leadtime_days as facets ---

$path = $dataDir . '/product_search_attribute.csv';
$csv = readCsv($path);
$existingKeys = array_column($csv['rows'], 'key');
$nextPosition = 1 + max(array_map('intval', array_column($csv['rows'], 'position') ?: [0]));

$newFacets = [
    ['key' => 'limitrange', 'filter_type' => 'multi-select', 'key.en_US' => 'Trip Temperature', 'key.de_DE' => 'Auslösetemperatur'],
    ['key' => 'packaging_unit', 'filter_type' => 'multi-select', 'key.en_US' => 'Packaging Unit', 'key.de_DE' => 'Verpackungseinheit'],
    ['key' => 'leadtime_days', 'filter_type' => 'range', 'key.en_US' => 'Lead Time (days)', 'key.de_DE' => 'Lieferzeit (Tage)'],
];
$added = 0;

foreach ($newFacets as $facet) {
    if (in_array($facet['key'], $existingKeys, true)) {
        continue;
    }

    $csv['rows'][] = $facet + ['position' => (string)$nextPosition];
    $nextPosition++;
    $added++;
}

if ($added > 0) {
    writeCsv($path, $csv['header'], $csv['rows']);
}

echo "product_search_attribute.csv: $added row(s) added\n";

// --- 3. product_concrete.csv: edit STL-7010 and HP-ECO-45K concretes ---

/**
 * concrete_sku => [attribute_key_2 => value, is_searchable => bool|null (null = leave unchanged)].
 * STL-7010's limitrange/packaging_unit go in slots 1/2; HP-ECO-45K's poweroutput (pre-existing, slot 1)
 * is left untouched, leadtime_days goes in slot 2.
 *
 * @var array<string, array{slot1?: array{key: string, value: string}, slot2?: array{key: string, value: string}, isSearchable?: bool}> $concreteEdits
 */
$concreteEdits = [
    'STL-7010-1' => ['slot1' => ['key' => 'limitrange', 'value' => '90°C'], 'slot2' => ['key' => 'packaging_unit', 'value' => 'Item']],
    'STL-7010-2' => ['slot1' => ['key' => 'limitrange', 'value' => '90°C'], 'slot2' => ['key' => 'packaging_unit', 'value' => '5-pack']],
    'STL-7010-3' => ['slot1' => ['key' => 'limitrange', 'value' => '90°C'], 'slot2' => ['key' => 'packaging_unit', 'value' => 'Box'], 'isSearchable' => false],
    'STL-7010-4' => ['slot1' => ['key' => 'limitrange', 'value' => '130°C'], 'slot2' => ['key' => 'packaging_unit', 'value' => 'Item'], 'isSearchable' => false],
    'STL-7010-5' => ['slot1' => ['key' => 'limitrange', 'value' => '130°C'], 'slot2' => ['key' => 'packaging_unit', 'value' => '5-pack']],
    'STL-7010-6' => ['slot1' => ['key' => 'limitrange', 'value' => '130°C'], 'slot2' => ['key' => 'packaging_unit', 'value' => 'Box']],
    'HP-ECO-45K-1' => ['slot2' => ['key' => 'leadtime_days', 'value' => '14']],
    'HP-ECO-45K-2' => ['slot2' => ['key' => 'leadtime_days', 'value' => '21']],
    'HP-ECO-45K-3' => ['slot2' => ['key' => 'leadtime_days', 'value' => '10']],
];

$path = $dataDir . '/product_concrete.csv';
$csv = readCsv($path);
$changed = 0;

foreach ($csv['rows'] as &$row) {
    $edit = $concreteEdits[$row['concrete_sku']] ?? null;

    if ($edit === null) {
        continue;
    }

    $rowChanged = false;

    foreach (['slot1', 'slot2'] as $i => $slotName) {
        if (!isset($edit[$slotName])) {
            continue;
        }

        $slotNum = $i + 1;
        $key = $edit[$slotName]['key'];
        $value = $edit[$slotName]['value'];

        foreach (['', '.de_DE', '.en_US'] as $localeSuffix) {
            $keyField = "attribute_key_{$slotNum}{$localeSuffix}";
            $valueField = "value_{$slotNum}{$localeSuffix}";

            if (!(($row[$keyField] ?? null) !== $key) && !(($row[$valueField] ?? null) !== $value)) {
                continue;
            }

            $row[$keyField] = $key;
            $row[$valueField] = $value;
            $rowChanged = true;
        }
    }

    if (array_key_exists('isSearchable', $edit)) {
        $wanted = $edit['isSearchable'] ? '1' : '0';

        foreach (['is_searchable.de_DE', 'is_searchable.en_US'] as $field) {
            if (!(($row[$field] ?? null) !== $wanted)) {
                continue;
            }

            $row[$field] = $wanted;
            $rowChanged = true;
        }
    }

    if (!$rowChanged) {
        continue;
    }

    $changed++;
}
unset($row);

if ($changed > 0) {
    writeCsv($path, $csv['header'], $csv['rows']);
}

echo "product_concrete.csv: $changed row(s) changed\n";

echo "\nDone. Now run (from the demoshop root):\n";
echo "  ./docker/sdk console data:import product-attribute-key\n";
echo "  ./docker/sdk console data:import product-search-attribute\n";
echo "  ./docker/sdk console data:import product-concrete\n";
echo "  ./docker/sdk console publish:trigger-events -r product_abstract -i <STL-7010's and HP-ECO-45K's id_product_abstract>\n";
echo "  ./docker/sdk console queue:worker:start --stop-when-empty\n";
