<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$dashboardGroups = require __DIR__ . '/groups.php';
$statusFile = $config['status_file'];
$objectsDir = $config['objects_dir'];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!is_readable($statusFile)) {
    http_response_code(500);
    echo json_encode(['error' => "Impossible de lire $statusFile"], JSON_UNESCAPED_UNICODE);
    exit;
}

function parseBlocks(array $lines, string $blockType): array {
    $result = [];
    $inside = false;
    $current = [];
    foreach ($lines as $line) {
        $trim = trim($line);
        if (preg_match('/^' . preg_quote($blockType, '/') . '\s*\{$/', $trim)) {
            $inside = true;
            $current = [];
            continue;
        }
        if ($inside && $trim === '}') {
            if ($current) $result[] = $current;
            $inside = false;
            $current = [];
            continue;
        }
        if ($inside && preg_match('/^([^=]+)=(.*)$/', $trim, $m)) {
            $current[trim($m[1])] = trim($m[2]);
        }
    }
    return $result;
}

function splitList(string $value): array {
    $value = trim($value);
    if ($value === '') return [];
    return array_values(array_filter(array_map('trim', preg_split('/[;,]+/', $value) ?: []), static fn($v) => $v !== ''));
}

function parseNagiosHostgroups(string $objectsDir): array {
    $membership = [];
    $files = glob(rtrim($objectsDir, '/') . '/*.cfg') ?: [];

    foreach ($files as $file) {
        if (!is_readable($file)) continue;
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) continue;
        foreach (parseBlocks($lines, 'host') as $host) {
            if (!isset($host['host_name'])) continue;
            $name = $host['host_name'];
            $membership[$name] = splitList($host['hostgroups'] ?? '');
        }
    }
    return $membership;
}

function hostState(array $h): string {
    return match ((int)($h['current_state'] ?? 3)) {
        0 => 'UP', 1 => 'DOWN', default => 'UNKNOWN'
    };
}

function serviceState(array $s): string {
    return match ((int)($s['current_state'] ?? 3)) {
        0 => 'OK', 1 => 'WARNING', 2 => 'CRITICAL', default => 'UNKNOWN'
    };
}

/* Valeurs utiles extraites de plugin_output, sans dépendre de performance_data. */
function outputMetrics(string $service, string $output): array {
    $metrics = [];
    $svc = strtoupper(trim($service));

    if ($svc === 'CPU') {
        if (preg_match('/average\s+load\s+([0-9]+(?:\.[0-9]+)?)%/i', $output, $m)) {
            $metrics[] = ['label' => 'cpu', 'value' => (float)$m[1], 'unit' => '%'];
        } elseif (preg_match('/CPU[^0-9]*([0-9]+(?:\.[0-9]+)?)%/i', $output, $m)) {
            $metrics[] = ['label' => 'cpu', 'value' => (float)$m[1], 'unit' => '%'];
        }
    }

    if ($svc === 'RAM') {
        if (preg_match('/Physical\s+Memory:\s*([0-9]+(?:\.[0-9]+)?)%\s*used/i', $output, $m)) {
            $metrics[] = ['label' => 'ram', 'value' => (float)$m[1], 'unit' => '%'];
        } elseif (preg_match('/RAM\s+ESXi:\s*([0-9]+(?:\.[0-9]+)?)%\s*utilisée/i', $output, $m)) {
            $metrics[] = ['label' => 'ram', 'value' => (float)$m[1], 'unit' => '%'];
        }
    }

    if (preg_match('/^(?:DISK|DISQUE)\s+(.+)$/i', $service, $sm)) {
        if (preg_match('/([0-9]+(?:\.[0-9]+)?)%\s*used/i', $output, $m)) {
            $metrics[] = ['label' => trim($sm[1]), 'value' => (float)$m[1], 'unit' => '%'];
        }
    }

    if ($svc === 'DATASTORES') {
        if (preg_match_all('/([A-Za-z0-9_.:-]+):\s*([0-9]+(?:\.[0-9]+)?)%\s*libre\b/iu', $output, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $free = (float)$m[2];
                $metrics[] = [
                    'label' => $m[1],
                    'value' => max(0.0, min(100.0, 100.0 - $free)),
                    'unit' => '%',
                    'free' => $free,
                ];
            }
        }
    }
    return $metrics;
}

function serviceData(array $s): array {
    $output = (string)($s['plugin_output'] ?? '');
    return [
        'service' => $s['service_description'],
        'state' => serviceState($s),
        'state_id' => (int)($s['current_state'] ?? 3),
        'output' => $output,
        'perfdata' => (string)($s['performance_data'] ?? ''),
        'metrics' => outputMetrics($s['service_description'], $output),
        'last_check' => isset($s['last_check']) ? (int)$s['last_check'] : 0,
    ];
}

$lines = file($statusFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$hosts = parseBlocks($lines, 'hoststatus');
$services = parseBlocks($lines, 'servicestatus');
$hostgroups = parseNagiosHostgroups($objectsDir);

$servicesByHost = [];
$summary = [
    'hosts' => ['total'=>count($hosts),'up'=>0,'down'=>0,'other'=>0],
    'services' => ['total'=>count($services),'ok'=>0,'warning'=>0,'critical'=>0,'unknown'=>0],
];

foreach ($hosts as $h) {
    $st = hostState($h);
    if ($st === 'UP') $summary['hosts']['up']++;
    elseif ($st === 'DOWN') $summary['hosts']['down']++;
    else $summary['hosts']['other']++;
}
foreach ($services as $s) {
    $st = serviceState($s);
    $summary['services'][strtolower($st)]++;
    $servicesByHost[$s['host_name']][] = serviceData($s);
}

$allHosts = [];
foreach ($hosts as $h) {
    $name = $h['host_name'];
    $data = [
        'name' => $name,
        'alias' => $h['alias'] ?? $name,
        'address' => $h['address'] ?? '',
        'state' => hostState($h),
        'state_id' => (int)($h['current_state'] ?? 3),
        'hostgroups' => $hostgroups[$name] ?? [],
        'last_check' => isset($h['last_check']) ? (int)$h['last_check'] : 0,
        'services' => $servicesByHost[$name] ?? [],
        'metrics' => ['cpu'=>null, 'ram'=>null, 'storage'=>[]],
    ];

    foreach ($data['services'] as $s) {
        $svc = strtoupper($s['service']);
        foreach ($s['metrics'] as $metric) {
            if ($svc === 'CPU' && $data['metrics']['cpu'] === null) $data['metrics']['cpu'] = $metric['value'];
            if ($svc === 'RAM' && $data['metrics']['ram'] === null) $data['metrics']['ram'] = $metric['value'];
            if (preg_match('/^(DISK|DISQUE)\s+(.+)$/i', $s['service'], $m)) {
                $data['metrics']['storage'][] = ['name'=>$metric['label'], 'value'=>$metric['value'], 'service'=>$s['service']];
            }
            if ($svc === 'DATASTORES') {
                $data['metrics']['storage'][] = ['name'=>$metric['label'], 'value'=>$metric['value'], 'free'=>$metric['free'] ?? null, 'service'=>$s['service']];
            }
        }
    }
    $allHosts[] = $data;
}

/* Construction des onglets depuis groups.php. */
$groups = [];
foreach ($dashboardGroups as $id => $definition) {
    $wantedGroups = array_map('strtolower', $definition['hostgroups'] ?? []);
    $wantedHosts = $definition['hosts'] ?? [];
    $groups[$id] = [
        'id' => $id,
        'label' => $definition['label'] ?? $id,
        'icon' => $definition['icon'] ?? '',
        'hosts' => [],
    ];

    foreach ($allHosts as $host) {
        $hostNameMatch = in_array($host['name'], $wantedHosts, true);
        $hostGroupMatch = false;
        foreach ($host['hostgroups'] as $hg) {
            if (in_array(strtolower($hg), $wantedGroups, true)) {
                $hostGroupMatch = true;
                break;
            }
        }
        if ($hostNameMatch || $hostGroupMatch) $groups[$id]['hosts'][] = $host;
    }
}

$switchChecks = [];
foreach (($groups['reseau']['hosts'] ?? []) as $h) {
    foreach ($h['services'] as $s) {
        $switchChecks[] = [
            'host' => $h['name'], 'alias' => $h['alias'], 'service' => $s['service'],
            'state' => $s['state'], 'output' => $s['output'], 'last_check' => $s['last_check'],
        ];
    }
}

$summary['groups'] = [];
foreach ($groups as $id => $group) $summary['groups'][$id] = count($group['hosts']);

// Les hôtes non affectés restent visibles dans un onglet automatique.
$assigned = [];
foreach ($groups as $group) foreach ($group['hosts'] as $h) $assigned[$h['name']] = true;
$others = array_values(array_filter($allHosts, static fn($h) => !isset($assigned[$h['name']])));
if ($others) {
    $groups['autres'] = ['id'=>'autres','label'=>'Autres','icon'=>'📦','hosts'=>$others];
    $summary['groups']['autres'] = count($others);
}

// Liste brute utile pour diagnostiquer rapidement un classement.
$debug = [
    'objects_dir_readable' => is_readable($objectsDir),
    'hostgroup_memberships_found' => count($hostgroups),
];

foreach ($groups as $id => &$group) unset($group['hosts']['hostgroups']);
unset($group);

// Reconstitue les groupes sans toucher aux données des hôtes.
echo json_encode([
    'generated' => date(DATE_ATOM),
    'refresh' => (int)$config['refresh_seconds'],
    'source' => 'Nagios status.dat / plugin_output + hostgroups Nagios',
    'summary' => $summary,
    'groups' => $groups,
    'switch_checks' => $switchChecks,
    'hosts' => $allHosts,
    'debug' => $debug,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
