<?php

return [
    'db' => [
        'host' => $_ENV['DB_HOST'],
        'dbname' => $_ENV['DB_NAME'],
        'user' => $_ENV['DB_USER'],
        'pass' => $_ENV['DB_PASS'],
    ],
    'api_key' => $_ENV['API_KEY'],
    'api_secret' => $_ENV['API_SECRET'],
    'use_hash_validation' => filter_var($_ENV['USE_HASH_VALIDATION'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'base_currency' => $_ENV['BASE_CURRENCY'],
];