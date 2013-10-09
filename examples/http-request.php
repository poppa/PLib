<?php
require_once '../src/PLib.php';

PLib\import ('net');

$cookiejar = new PLib\Net\HTTPCookie ('cookies.cki', PLIB_TMP_DIR);

$cli = new PLib\Net\HTTPRequest ($cookiejar);
$cli->cache (60);
$resp = $cli->get ('http://www.expressen.se');

//echo $resp->data () . "\n";
?>