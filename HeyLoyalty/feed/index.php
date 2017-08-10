<?php
ini_set ('max_execution_time', 600);
ob_clean();
$sep = DIRECTORY_SEPARATOR;

include(__DIR__ . $sep . '..' . $sep . '..' . $sep . '..' . $sep . 'config' . $sep . 'config.inc.php');
include(__DIR__ . $sep . '..' . $sep . '..' . $sep . '..' . $sep . 'init.php');
include(__DIR__ . $sep . 'HeyloyaltyFeed.php');

header('Content-Type: application/json');
$hlService = new HeyloyaltyFeed();
echo $hlService->generateProductFeed(substr(Tools::getValue('lang'), 0, 2), substr(Tools::getValue('currency'), 0, 3));
