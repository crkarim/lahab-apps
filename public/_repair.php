<?php
/**
 * EMERGENCY REPAIR SCRIPT — token-gated, framework-independent.
 *
 * Reason this exists: when a deploy adds new PHP class files (new
 * controllers / helpers / etc.) on a host whose Composer was set up
 * with `classmap-authoritative` or whose APCu cache hasn't expired,
 * those new classes raise "Class not found" 500s on EVERY page,
 * including /admin/maintenance — leaving the admin unrecoverable from
 * inside the framework.
 *
 * This script bypasses the framework: walks composer.json's PSR-4
 * config, rebuilds vendor/composer/autoload_classmap.php with every
 * class on disk, and clears bootstrap/cache so Laravel reboots clean.
 *
 * Auth: reads MAINTENANCE_TOKEN from .env (no Laravel boot needed).
 *
 * USAGE:
 *   1. Visit /storage-public/_repair.php?token=YOUR_TOKEN
 *      (Or just /_repair.php depending on how public/ is mapped.)
 *   2. Wait ~2s for the report.
 *   3. Confirm admin panel is back up.
 *   4. DELETE this file from cPanel File Manager.
 *
 * Safe by default:
 *   - Only writes ONE file (vendor/composer/autoload_classmap.php).
 *   - Only deletes Laravel's regenerable bootstrap/cache files.
 *   - Read-only otherwise.
 */

@ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

$base = realpath(__DIR__ . '/..');
if ($base === false || !is_dir($base . '/app')) {
    http_response_code(500);
    echo "Cannot locate app root. Expected ../app from this script's location.\n";
    exit;
}

// --- 1. Read MAINTENANCE_TOKEN out of .env without booting Laravel.
$expectedToken = '';
$envPath = $base . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath) as $line) {
        if (preg_match('/^\s*MAINTENANCE_TOKEN\s*=\s*(.*)$/', $line, $m)) {
            $expectedToken = trim($m[1], "\"' \t\r\n");
            break;
        }
    }
}

$givenToken = $_GET['token'] ?? $_POST['token'] ?? '';
if ($expectedToken === '' || !is_string($givenToken) || !hash_equals($expectedToken, $givenToken)) {
    http_response_code(401);
    echo "Forbidden — token missing or mismatch.\n";
    echo "Pass it as ?token=YOUR_TOKEN in the URL (matches MAINTENANCE_TOKEN in .env).\n";
    exit;
}

echo "=== Lahab admin emergency repair ===\n";
echo "App root: $base\n\n";

// --- 2. Sanity-check that the expected new files actually exist on disk.
$mustExist = [
    'app/Http/Controllers/Admin/KitchenScanController.php',
    'app/CentralLogics/WaiterPushHelper.php',
    'resources/views/admin-views/kitchen/scan.blade.php',
    'database/migrations/2026_04_30_124606_add_ready_columns_to_orders.php',
];
echo "=== File existence check ===\n";
$allFound = true;
foreach ($mustExist as $rel) {
    $exists = is_file($base . '/' . $rel);
    echo ($exists ? "  OK   " : "  MISS ") . $rel . "\n";
    if (!$exists) $allFound = false;
}
if (!$allFound) {
    echo "\nOne or more files missing. Re-run cPanel Git → Update from Remote, then refresh this page.\n";
    exit;
}

// --- 3. Rebuild the composer classmap by walking PSR-4 dirs.
echo "\n=== Rebuilding composer classmap ===\n";

$composerJson = $base . '/composer.json';
if (!is_file($composerJson)) {
    echo "  composer.json not found — aborting.\n";
    exit;
}
$cfg = json_decode(file_get_contents($composerJson), true);
$psr4 = $cfg['autoload']['psr-4'] ?? [];
if (empty($psr4)) {
    echo "  No PSR-4 config in composer.json — nothing to rebuild.\n";
    exit;
}

$discovered = [];
foreach ($psr4 as $prefix => $dirs) {
    if (is_string($dirs)) $dirs = [$dirs];
    foreach ($dirs as $dir) {
        $absDir = $base . '/' . rtrim($dir, '/');
        if (!is_dir($absDir)) continue;
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($rii as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $rel = ltrim(substr($file->getPathname(), strlen($absDir)), '/\\');
            $rel = str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $rel);
            $class = $prefix . $rel;
            // Verify the file actually declares this class to avoid
            // poisoning the classmap with stale/invalid entries.
            $tokens = @token_get_all(@file_get_contents($file->getPathname()));
            $declares = false;
            for ($i = 0, $n = count($tokens); $i < $n; $i++) {
                if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                    // Class anonymous detection — skip `new class` etc.
                    $j = $i - 1;
                    while ($j > 0 && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_DOC_COMMENT, T_COMMENT], true)) {
                        $j--;
                    }
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_NEW) continue;
                    $declares = true;
                    break;
                }
            }
            if ($declares) {
                $discovered[$class] = $file->getPathname();
            }
        }
    }
}

// Merge with the existing classmap (which has vendor classes too).
$classmapFile = $base . '/vendor/composer/autoload_classmap.php';
$existing = is_file($classmapFile) ? (include $classmapFile) : [];
if (!is_array($existing)) $existing = [];

$merged = $existing;
foreach ($discovered as $class => $path) {
    $merged[$class] = $path;
}
ksort($merged);

// Write the new classmap.
$php  = "<?php\n\n// autoload_classmap.php @generated by /_repair.php (" . date('c') . ")\n\n";
$php .= "\$vendorDir = dirname(__DIR__);\n";
$php .= "\$baseDir = dirname(\$vendorDir);\n\nreturn array(\n";
foreach ($merged as $class => $path) {
    if (strpos($path, $base) === 0) {
        $rel = ltrim(substr($path, strlen($base)), '/\\');
        $php .= "    " . var_export($class, true) . " => \$baseDir . '/" . str_replace('\\', '/', $rel) . "',\n";
    } else {
        $php .= "    " . var_export($class, true) . " => " . var_export($path, true) . ",\n";
    }
}
$php .= ");\n";

$bytes = @file_put_contents($classmapFile, $php);
if ($bytes === false) {
    echo "  ERROR: could not write $classmapFile (check file permissions).\n";
    exit;
}
echo "  Wrote " . count($merged) . " entries (" . count($discovered) . " app classes) to autoload_classmap.php\n";
echo "  Size: " . number_format($bytes) . " bytes\n";

// --- 4. Clear Laravel's bootstrap/cache so it re-reads everything.
echo "\n=== Clearing bootstrap/cache ===\n";
$cacheFiles = [
    'bootstrap/cache/config.php',
    'bootstrap/cache/services.php',
    'bootstrap/cache/packages.php',
];
foreach ($cacheFiles as $rel) {
    $abs = $base . '/' . $rel;
    if (is_file($abs)) {
        if (@unlink($abs)) {
            echo "  removed $rel\n";
        } else {
            echo "  WARN: could not remove $rel\n";
        }
    } else {
        echo "  skip   $rel (not present)\n";
    }
}
// Kill any cached compiled routes too.
foreach (glob($base . '/bootstrap/cache/routes-*.php') as $rf) {
    @unlink($rf);
    echo "  removed " . basename($rf) . "\n";
}

echo "\n=== Done ===\n";
echo "Try opening the admin panel now: https://office.lahab.com.bd/admin\n";
echo "If it loads, DELETE this file (public/_repair.php) from cPanel File Manager —\n";
echo "it's token-gated but safer to remove once the admin is healthy.\n";
echo "\nNeed migration applied too? After admin is back up, hit /admin/maintenance.\n";
