<?php

return [
    'base_url' => env('ERETAIL_BASE_URL', 'http://162.62.125.25:5003'),
    'username' => env('ERETAIL_USERNAME', 'Sunytest'),
    'password' => env('ERETAIL_PASSWORD', '12345678'),
    'default_shop_code' => env('ERETAIL_DEFAULT_SHOP_CODE', 'C010'),
    'timeout' => env('ERETAIL_TIMEOUT', 30),
    'retry_times' => env('ERETAIL_RETRY_TIMES', 3),
    'retry_delay' => env('ERETAIL_RETRY_DELAY', 1000), // milliseconds
];