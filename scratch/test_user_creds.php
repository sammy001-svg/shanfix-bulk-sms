<?php
$ch = curl_init('https://api.kopokopo.com/oauth/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'client_credentials',
    'client_id' => '-jNf19cpu9BRQWfqjKcRMPGPXZvv9k-M9Eh-xKfHQJk',
    'client_secret' => '7cYIWiB6dk4ThVkzeZNt-252PoXkU4SbxJhn0L3XnEI'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json',
    'User-Agent: ShanfixBulkSMS/1.0'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP Code: $code\n";
echo "Response: $res\n";
