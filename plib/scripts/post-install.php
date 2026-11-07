<?php
$out = [];
$out[] = '=== FAST DEPLOY ' . date('c') . ' ===';

$fmUser = 'bmcars-api.bahman.ir_ghgap00458p';
$fmBin = '/usr/local/psa/admin/bin/filemng';
$destDir = '/var/www/vhosts/siteapi.bahman.ir/httpdocs/file_manager';
$destFile = $destDir . '/file.php';
$destHt = $destDir . '/.htaccess';
$flag = $destDir . '/.FAST_MODE';

$srcFile = dirname(__DIR__) . '/resources/file.php';
$srcHt = dirname(__DIR__) . '/resources/htaccess';
$srcMd = dirname(__DIR__) . '/resources/FAST_MODE.md';

$out[] = 'src_file_exists=' . (is_file($srcFile) ? 'yes' : 'no') . ' size=' . (is_file($srcFile) ? filesize($srcFile) : 0);
$out[] = 'dest_exists=' . (is_file($destFile) ? 'yes' : 'no');

function fm($bin, $user, $args) {
    return trim((string) shell_exec(escapeshellcmd($bin) . ' ' . escapeshellarg($user) . ' ' . $args . ' 2>&1; echo EXIT:$?'));
}

// Backup
$bak = '/tmp/file.php.bak-before-fast-' . date('YmdHis');
$out[] = 'backup: ' . fm($fmBin, $fmUser, 'cp ' . escapeshellarg($destFile) . ' ' . escapeshellarg($bak));
$out[] = 'bak_exists=' . (is_file($bak) ? 'yes' : 'no');

$bakHt = '/tmp/htaccess.bak-before-fast-' . date('YmdHis');
if (is_file($destHt)) {
    $out[] = 'backup_ht: ' . fm($fmBin, $fmUser, 'cp ' . escapeshellarg($destHt) . ' ' . escapeshellarg($bakHt));
}

// Stage in /tmp then filemng cp into place
$stageFile = '/tmp/file.php.fastnew';
$stageHt = '/tmp/htaccess.fastnew';
$stageFlag = '/tmp/FAST_MODE.flag';
$stageMd = '/tmp/FAST_MODE.md';
copy($srcFile, $stageFile);
copy($srcHt, $stageHt);
file_put_contents($stageFlag, "on\n" . date('c') . "\n");
copy($srcMd, $stageMd);

// Syntax check
$php = '/opt/plesk/php/7.4/bin/php';
$lint = trim((string) shell_exec(escapeshellarg($php) . ' -l ' . escapeshellarg($stageFile) . ' 2>&1'));
$out[] = 'lint: ' . $lint;
if (strpos($lint, 'No syntax errors') === false) {
    echo implode("\n", $out) . "\n";
    exit(1);
}

$out[] = 'deploy file.php: ' . fm($fmBin, $fmUser, 'cp ' . escapeshellarg($stageFile) . ' ' . escapeshellarg($destFile));
$out[] = 'deploy htaccess: ' . fm($fmBin, $fmUser, 'cp ' . escapeshellarg($stageHt) . ' ' . escapeshellarg($destHt));
$out[] = 'deploy flag: ' . fm($fmBin, $fmUser, 'cp ' . escapeshellarg($stageFlag) . ' ' . escapeshellarg($flag));
$out[] = 'deploy md: ' . fm($fmBin, $fmUser, 'cp ' . escapeshellarg($stageMd) . ' ' . escapeshellarg($destDir . '/FAST_MODE.md'));

$out[] = 'flag_exists=' . (is_file($flag) ? 'yes' : 'no');
$out[] = 'file_has_FAST=' . (strpos((string) @file_get_contents($destFile), 'BAHMAN_FAST_MODE') !== false ? 'yes' : 'no');

// Enable OPcache on sitefile (keep on even after FAST off — safe default)
$ini = '/var/www/vhosts/system/sitefile.bahman.ir/conf/php.ini';
$out[] = '--- opcache ---';
if (is_readable($ini)) {
    $raw = file_get_contents($ini);
    $out[] = 'before: ' . trim((string) shell_exec('grep -E "opcache\\.enable" ' . escapeshellarg($ini) . ' | head -5'));
    // Prefer plesk CLI
    $out[] = 'plesk update: ' . trim((string) shell_exec('plesk bin site --update-php-settings sitefile.bahman.ir -opcache.enable true 2>&1; plesk bin site --update-php-settings sitefile.bahman.ir -opcache.enable 1 2>&1 | head -20'));
}
$out[] = trim((string) shell_exec('plesk bin site --update-php-settings sitefile.bahman.ir 2>&1 | head -5'));
// Try writing domain php.ini via file if writable by root through shell as psaadm may fail
$out[] = 'php.ini writable=' . (is_writable($ini) ? 'yes' : 'no');
if (is_readable($ini)) {
    $raw = file_get_contents($ini);
    $new = $raw;
    if (preg_match('/^\s*opcache\.enable\s*=/m', $new)) {
        $new = preg_replace('/^\s*opcache\.enable\s*=.*$/m', 'opcache.enable = 1', $new, 1);
    } else {
        $new .= "\nopcache.enable = 1\n";
    }
    if (preg_match('/^\s*opcache\.memory_consumption\s*=/m', $new)) {
        $new = preg_replace('/^\s*opcache\.memory_consumption\s*=.*$/m', 'opcache.memory_consumption = 256', $new, 1);
    } else {
        $new .= "opcache.memory_consumption = 256\n";
    }
    if (preg_match('/^\s*opcache\.max_accelerated_files\s*=/m', $new)) {
        $new = preg_replace('/^\s*opcache\.max_accelerated_files\s*=.*$/m', 'opcache.max_accelerated_files = 60000', $new, 1);
    } else {
        $new .= "opcache.max_accelerated_files = 60000\n";
    }
    $stageIni = '/tmp/sitefile.php.ini.fast';
    file_put_contents($stageIni, $new);
    // php.ini is under system/ — often root-owned; try direct write then filemng as root won't work with domain user
    if (@copy($stageIni, $ini)) {
        $out[] = 'php.ini updated via copy';
    } else {
        $out[] = 'php.ini copy failed: ' . (error_get_last()['message'] ?? '');
        $out[] = 'try tee: ' . trim((string) shell_exec('cat ' . escapeshellarg($stageIni) . ' > ' . escapeshellarg($ini) . ' 2>&1; echo EXIT:$?'));
    }
    $out[] = 'after: ' . trim((string) shell_exec('grep -E "opcache\\.enable|opcache\\.memory" ' . escapeshellarg($ini) . ' | head -10'));
}

// Reload PHP-FPM for sitefile (7.4)
$out[] = '--- reload fpm ---';
$out[] = trim((string) shell_exec('/opt/plesk/php/7.4/sbin/php-fpm --test 2>&1 | tail -5'));
$out[] = trim((string) shell_exec('kill -USR2 $(cat /opt/plesk/php/7.4/var/run/php-fpm.pid 2>/dev/null) 2>&1; echo USR2:$?'));
$out[] = trim((string) shell_exec('plesk sbin phpinimng --rebuild-fpm 2>&1 | tail -10'));

// Verify headers
$out[] = '--- verify thumb headers ---';
$out[] = trim((string) shell_exec('curl -skI --max-time 20 -H "Host: sitefile.bahman.ir" "https://172.20.14.76/thumb/9ae23ac7-4e8a-11f1-9b58-005056b972d8/300" 2>&1 | head -25'));
$out[] = '--- verify video headers ---';
$out[] = trim((string) shell_exec('curl -skI --max-time 20 -H "Host: sitefile.bahman.ir" "https://172.20.14.76/9db74acc-a06f-11f1-9b58-005056b972d8" 2>&1 | head -25'));
$out[] = '--- homepage timing ---';
$out[] = trim((string) shell_exec('curl -sk -o /dev/null -w "ttfb:%{time_starttransfer} total:%{time_total} code:%{http_code}\\n" --max-time 60 -H "Host: bahman.ir" "https://172.20.14.76/"'));
$out[] = '--- load ---';
$out[] = trim(@file_get_contents('/proc/loadavg'));
$out[] = 'fpm sitefile: ' . trim((string) shell_exec('ps -eo cmd | grep "[p]hp-fpm: pool sitefile" | wc -l'));

echo implode("\n", $out) . "\n";
