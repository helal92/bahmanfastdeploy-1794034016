<?php
$fm='/usr/local/psa/admin/bin/filemng';
$user='bmcars-api.bahman.ir_ghgap00458p';
$flag='/var/www/vhosts/siteapi.bahman.ir/httpdocs/file_manager/.FAST_MODE';
echo shell_exec(escapeshellcmd($fm).' '.escapeshellarg($user).' rm '.escapeshellarg($flag).' 2>&1');
echo "\nOFF exists=".(is_file($flag)?'yes':'no')."\n";
