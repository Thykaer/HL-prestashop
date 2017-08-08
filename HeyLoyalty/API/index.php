<?php set_time_limit(0);

include(dirname(__FILE__).'/../../../config/config.inc.php');

if (class_exists('GuzzleHttp\Client')) {
    $alternate = true;
} else {
    $alternate = false;
}

include(dirname(__FILE__).'/../HeyLoyalty.php');

$is_running = Configuration::get('HeyLoyalty_running_import');

$module = new HeyLoyalty();

$api = new HeyLoyaltyAPI($module->api_key, $module->api_secret);

$id_list = $_REQUEST['id_list'];

$only_subscribed = false;
if($_REQUEST['only_subscribed'] == 1){
    $only_subscribed = true;
}

try {
    $customers = $module->getExcelData($id_list, $only_subscribed);
    if (!$is_running) {
        Configuration::updateValue('HeyLoyalty_running_import', $_SERVER['REMOTE_ADDR']);
    } elseif($is_running != $_SERVER['REMOTE_ADDR']) {
        die('import is already running');
    }

    $remains = $api->add_member_in_bulk($id_list, $customers, $module);
    $remains = (int) $remains;
    $cookies_name = 'import_progress_' . $id_list;
    if ($remains <= 0) {
        $_COOKIE[$cookies_name] = 0;
        echo 'Import Finished';
    } else {
        echo $_COOKIE[$cookies_name] . ' customers imported<br/>';
        echo $remains . ' customers remaining to import';
    }

    Configuration::updateValue('HeyLoyalty_running_import', 0);
} catch (Exception $e) {
}
