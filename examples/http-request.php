<?php
require_once '../src/PLib.php';

PLib\import ('net');

$cookiejar = new PLib\Net\HTTPCookie ('cookies.cki', PLIB_TMP_DIR);

$cli = new PLib\Net\HTTPRequest ($cookiejar);
$cli->cache (60);
$resp = $cli->get ('http://www.google.com');

echo (string) $resp . "\n";
?>