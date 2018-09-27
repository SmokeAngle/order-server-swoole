<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/const.php';

use OrderServer\Libs\Console;


$console = new OrderServer\Libs\Console(APP_SERVER_CONFIG, APP_CONFIG);
$console->run();

