<?php
require_once dirname(__DIR__) . '/includes/db.php';

$settings = [
    'payhero_api_username' => '',
    'payhero_api_password' => '',
    'payhero_api_channel_id' => ''
];

foreach ($settings as $key => $val) {
    DB::execute("INSERT IGNORE INTO system_settings (`key`, `value`) VALUES (?, ?)", [$key, $val]);
}

echo "Payhero settings initialized.\n";
