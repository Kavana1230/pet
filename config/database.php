<?php

return [
    'host' => getenv('PAWNET_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('PAWNET_DB_PORT') ?: '3306',
    'database' => getenv('PAWNET_DB_NAME') ?: 'pawnet',
    'username' => getenv('PAWNET_DB_USER') ?: 'root',
    'password' => getenv('PAWNET_DB_PASSWORD') !== false ? getenv('PAWNET_DB_PASSWORD') : '',
];
