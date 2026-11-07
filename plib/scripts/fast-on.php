<?php
$fm='/usr/local/psa/admin/bin/filemng';
$user='bmcars-api.bahman.ir_ghgap00458p';
$flag='/var/www/vhosts/siteapi.bahman.ir/httpdocs/file_manager/.FAST_MODE';
file_put_contents('/tmp/FAST_MODE.flag', "on\n".date('c')."\n");
echo shell_exec(escapeshellcmd($fm).' '.escapeshellarg($user).' cp '.escapeshellarg('/tmp/FAST_MODE.flag').' '.escapeshellarg($flag).' 2>&1');
echo "\nON exists=".(is_file($flag)?'yes':'no')."\n";
