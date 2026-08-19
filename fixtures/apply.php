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
 * TWO fixtures live here side by side, on purpose:
 *
 * 1. The ORIGINAL real-product fixture (STL-7010, HP-ECO-45K) that this package's own Presentation test
 *    suite (`CrossFacetAndCest`, `RangeFacetCest`) already asserts against — kept as-is, since ripping it
 *    out would break that suite for anyone following this README.
 * 2. A newer fixture built on "Feldwerk", the shared FICTIONAL demo catalog (see the sibling
 *    search-debug/search-feedback/search-ranking/search-ranking-optimizer/search-analyzer-config
 *    packages' own fixtures), for this package's own README screenshots — this demoshop's real product
 *    images/descriptions aren't covered under redistribution rights, so screenshots use this catalog
 *    instead. Adds 2 new concretes each to two already-fictional Feldwerk chairs. Reuses the demoshop's
 *    already-registered "farbe" (color) and "material" facets (both already `multi-select` in
 *    product_search_attribute.csv) rather than staking a new claim for them.
 *
 * DEMO-CHR-001 ("Feldwerk Stapelstuhl, 4-Fuß-Gestell") gets 2 new concretes alongside its existing one,
 * varying farbe × material with a DELIBERATELY INCOMPLETE 2x2 matrix (anthrazit+stoff, anthrazit+leder,
 * schwarz+stoff exist; schwarz+leder does not) — the same shape the STL-7010 fixture uses to prove the
 * cross-facet-AND bug: selecting farbe=schwarz AND material=leder together must return ZERO products,
 * where stock Spryker's OR-across-concretes facet index would incorrectly still match this abstract.
 *
 * DEMO-CHR-002 ("Feldwerk Stapelstuhl, gepolsterter Sitz") gets 2 new concretes varying leadtime_days
 * (14 / 21 / 28 days) — the SAME shared `leadtime_days` range facet HP-ECO-45K already uses — to
 * demonstrate the range facet's min/max slider and per-concrete range filtering.
 *
 * Usage: php fixtures/apply.php /path/to/b2b-demo-marketplace
 *
 * Then, from that demoshop checkout:
 *   ./docker/sdk console data:import product-attribute-key # MUST run before product-search-attribute
 *   ./docker/sdk console data:import product-search-attribute
 *   ./docker/sdk console data:import product-concrete
 *   ./docker/sdk console data:import product-stock
 *   ./docker/sdk console publish:trigger-events -r product_abstract -i <id_product_abstract of STL-7010, HP-ECO-45K, DEMO-CHR-001 and DEMO-CHR-002>
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

/**
 * Applies attribute_key_N/value_N edits (all 3 locale-suffixed variants) to already-existing
 * product_concrete.csv rows, matched by concrete_sku.
 *
 * @param array<int, array<string, string>> $rows
 * @param array<string, array{slot1?: array{key: string, value: string}, slot2?: array{key: string, value: string}}> $edits
 *
 * @return int Number of rows changed.
 */
function applyConcreteAttributeEdits(array &$rows, array $edits): int
{
    $changed = 0;

    foreach ($rows as &$row) {
        $edit = $edits[$row['concrete_sku']] ?? null;

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

                if (($row[$keyField] ?? null) === $key && ($row[$valueField] ?? null) === $value) {
                    continue;
                }

                $row[$keyField] = $key;
                $row[$valueField] = $value;
                $rowChanged = true;
            }
        }

        if (isset($edit['isSearchable'])) {
            $wanted = $edit['isSearchable'] ? '1' : '0';

            foreach (['is_searchable.de_DE', 'is_searchable.en_US'] as $field) {
                if (($row[$field] ?? null) !== $wanted) {
                    $row[$field] = $wanted;
                    $rowChanged = true;
                }
            }
        }

        if ($rowChanged) {
            $changed++;
        }
    }
    unset($row);

    return $changed;
}

// --- 1. product_attribute_key.csv: register this package's 3 attribute-key claims ---
// ("farbe" and "material", used by the Feldwerk fixture below, are already registered, real demoshop
// facets -- reused, not re-claimed.)

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
$nextPosition = 1 + max(array_map(fn (mixed $value): int => (int)$value, array_column($csv['rows'], 'position') ?: [0]));

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

// --- 3. product_concrete.csv: edit STL-7010/HP-ECO-45K (real products, existing E2E fixture) ... ---

/**
 * concrete_sku => [attribute_key_2 => value, is_searchable => bool|null (null = leave unchanged)].
 * STL-7010's limitrange/packaging_unit go in slots 1/2; HP-ECO-45K's poweroutput (pre-existing, slot 1)
 * is left untouched, leadtime_days goes in slot 2.
 *
 * @var array<string, array{slot1?: array{key: string, value: string}, slot2?: array{key: string, value: string}, isSearchable?: bool}> $realProductConcreteEdits
 */
$realProductConcreteEdits = [
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

// --- ...and DEMO-CHR-001-1/DEMO-CHR-002-1 (Feldwerk, existing rows -- new variant siblings follow below) ---

/**
 * @var array<string, array{slot1?: array{key: string, value: string}, slot2?: array{key: string, value: string}}> $feldwerkConcreteEdits
 */
$feldwerkConcreteEdits = [
    'DEMO-CHR-001-1' => ['slot1' => ['key' => 'farbe', 'value' => 'anthrazit'], 'slot2' => ['key' => 'material', 'value' => 'Stoff']],
    'DEMO-CHR-002-1' => ['slot1' => ['key' => 'leadtime_days', 'value' => '14']],
];

/**
 * New Feldwerk concrete rows: DEMO-CHR-001 (farbe x material, deliberately missing schwarz+leder) and
 * DEMO-CHR-002 (leadtime_days).
 *
 * @var array<int, array<string, string>> $newFeldwerkConcreteRows
 */
$newFeldwerkConcreteRows = [
    [
        'abstract_sku' => 'DEMO-CHR-001',
        'concrete_sku' => 'DEMO-CHR-001-2',
        'name.de_DE' => 'Feldwerk Stapelstuhl, 4-Fuß-Gestell - anthrazit, Leder',
        'name.en_US' => 'Feldwerk stacking chair, 4-leg frame - anthracite, leather',
        'description.de_DE' => 'Feldwerk Stapelstuhl mit pulverbeschichtetem 4-Fuß-Stahlgestell, anthrazitfarbener Lederschale.',
        'description.en_US' => 'Feldwerk stacking chair with a powder-coated 4-leg steel frame, anthracite leather shell.',
        'is_searchable.de_DE' => '1',
        'is_searchable.en_US' => '1',
        'attribute_key_1' => 'farbe',
        'value_1' => 'anthrazit',
        'attribute_key_1.de_DE' => 'farbe',
        'value_1.de_DE' => 'anthrazit',
        'attribute_key_1.en_US' => 'farbe',
        'value_1.en_US' => 'anthracite',
        'attribute_key_2' => 'material',
        'value_2' => 'Leder',
        'attribute_key_2.de_DE' => 'material',
        'value_2.de_DE' => 'Leder',
        'attribute_key_2.en_US' => 'material',
        'value_2.en_US' => 'Leather',
        'is_active' => '1',
    ],
    [
        'abstract_sku' => 'DEMO-CHR-001',
        'concrete_sku' => 'DEMO-CHR-001-3',
        'name.de_DE' => 'Feldwerk Stapelstuhl, 4-Fuß-Gestell - schwarz, Stoff',
        'name.en_US' => 'Feldwerk stacking chair, 4-leg frame - black, fabric',
        'description.de_DE' => 'Feldwerk Stapelstuhl mit pulverbeschichtetem 4-Fuß-Stahlgestell, schwarzer Stoffschale.',
        'description.en_US' => 'Feldwerk stacking chair with a powder-coated 4-leg steel frame, black fabric shell.',
        'is_searchable.de_DE' => '1',
        'is_searchable.en_US' => '1',
        'attribute_key_1' => 'farbe',
        'value_1' => 'schwarz',
        'attribute_key_1.de_DE' => 'farbe',
        'value_1.de_DE' => 'schwarz',
        'attribute_key_1.en_US' => 'farbe',
        'value_1.en_US' => 'black',
        'attribute_key_2' => 'material',
        'value_2' => 'Stoff',
        'attribute_key_2.de_DE' => 'material',
        'value_2.de_DE' => 'Stoff',
        'attribute_key_2.en_US' => 'material',
        'value_2.en_US' => 'Fabric',
        'is_active' => '1',
    ],
    [
        'abstract_sku' => 'DEMO-CHR-002',
        'concrete_sku' => 'DEMO-CHR-002-2',
        'name.de_DE' => 'Feldwerk Stapelstuhl, gepolsterter Sitz - blau, 21 Tage',
        'name.en_US' => 'Feldwerk stacking chair, upholstered seat - blue, 21 days',
        'description.de_DE' => 'Feldwerk Stapelstuhl mit gepolstertem Sitzkissen aus blauem Stoff, Lieferzeit 21 Tage.',
        'description.en_US' => 'Feldwerk stacking chair with an upholstered seat pad in blue fabric, 21-day lead time.',
        'is_searchable.de_DE' => '1',
        'is_searchable.en_US' => '1',
        'attribute_key_1' => 'leadtime_days',
        'value_1' => '21',
        'attribute_key_1.de_DE' => 'leadtime_days',
        'value_1.de_DE' => '21',
        'attribute_key_1.en_US' => 'leadtime_days',
        'value_1.en_US' => '21',
        'is_active' => '1',
    ],
    [
        'abstract_sku' => 'DEMO-CHR-002',
        'concrete_sku' => 'DEMO-CHR-002-3',
        'name.de_DE' => 'Feldwerk Stapelstuhl, gepolsterter Sitz - blau, 28 Tage',
        'name.en_US' => 'Feldwerk stacking chair, upholstered seat - blue, 28 days',
        'description.de_DE' => 'Feldwerk Stapelstuhl mit gepolstertem Sitzkissen aus blauem Stoff, Lieferzeit 28 Tage.',
        'description.en_US' => 'Feldwerk stacking chair with an upholstered seat pad in blue fabric, 28-day lead time.',
        'is_searchable.de_DE' => '1',
        'is_searchable.en_US' => '1',
        'attribute_key_1' => 'leadtime_days',
        'value_1' => '28',
        'attribute_key_1.de_DE' => 'leadtime_days',
        'value_1.de_DE' => '28',
        'attribute_key_1.en_US' => 'leadtime_days',
        'value_1.en_US' => '28',
        'is_active' => '1',
    ],
];

$path = $dataDir . '/product_concrete.csv';
$csv = readCsv($path);

$changed = applyConcreteAttributeEdits($csv['rows'], $realProductConcreteEdits);
$changed += applyConcreteAttributeEdits($csv['rows'], $feldwerkConcreteEdits);

$existingSkus = array_column($csv['rows'], 'concrete_sku');

foreach ($newFeldwerkConcreteRows as $newRow) {
    if (in_array($newRow['concrete_sku'], $existingSkus, true)) {
        continue;
    }

    $csv['rows'][] = $newRow;
    $changed++;
}

if ($changed > 0) {
    writeCsv($path, $csv['header'], $csv['rows']);
}

echo "product_concrete.csv: $changed row(s) changed/added\n";

// --- 4. product_stock.csv: stock rows for the 4 new Feldwerk concretes ---
// (STL-7010/HP-ECO-45K already have stock; abstract-level price/image already cover the new Feldwerk
// concretes too, so no product_price.csv/product_image.csv changes are needed here.)

$path = $dataDir . '/product_stock.csv';
$csv = readCsv($path);
$existingSkus = array_column($csv['rows'], 'concrete_sku');
$added = 0;

foreach (['DEMO-CHR-001-2', 'DEMO-CHR-001-3', 'DEMO-CHR-002-2', 'DEMO-CHR-002-3'] as $sku) {
    if (in_array($sku, $existingSkus, true)) {
        continue;
    }

    $csv['rows'][] = ['concrete_sku' => $sku, 'name' => 'Warehouse1', 'quantity' => '50', 'is_never_out_of_stock' => '0', 'is_bundle' => '0'];
    $added++;
}

if ($added > 0) {
    writeCsv($path, $csv['header'], $csv['rows']);
}

echo "product_stock.csv: $added row(s) added\n";

echo "\nDone. Now run (from the demoshop root):\n";
echo "  ./docker/sdk console data:import product-attribute-key\n";
echo "  ./docker/sdk console data:import product-search-attribute\n";
echo "  ./docker/sdk console data:import product-concrete\n";
echo "  ./docker/sdk console data:import product-stock\n";
echo "  ./docker/sdk console publish:trigger-events -r product_abstract -i <id_product_abstract of STL-7010, HP-ECO-45K, DEMO-CHR-001 and DEMO-CHR-002>\n";
echo "  ./docker/sdk console queue:worker:start --stop-when-empty\n";
