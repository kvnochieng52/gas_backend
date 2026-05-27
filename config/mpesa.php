<?php

// MPESA_SANDBOX=false  → production  (https://api.safaricom.co.ke)
// MPESA_SANDBOX=true   → sandbox     (https://sandbox.safaricom.co.ke)
$isSandbox = filter_var(env('MPESA_SANDBOX', true), FILTER_VALIDATE_BOOLEAN);

return [
    'environment'     => $isSandbox ? 'sandbox' : 'production',
    'consumer_key'    => env('MPESA_CONSUMER_KEY', ''),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),
    'shortcode'       => env('MPESA_SHORT_CODE', env('MPESA_SHORTCODE', '174379')),
    'passkey'         => env('MPESA_PASSKEY', ''),
    'callback_url'    => env('MPESA_CALLBACK_URL', ''),
];
