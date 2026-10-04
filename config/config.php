<?php
declare(strict_types=1);

/*
 * Production configuration for REG.RU.
 *
 * IMPORTANT:
 * This file is committed only because the current deployment does not have
 * a separate secret/config mechanism. Replace DB_PASSWORD on the server/repo
 * with the actual MySQL password for u1027561_smetogram.
 */

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'u1027561_smetogram',
        'user' => 'u1027561_smetogram',
        'password' => 'DB_PASSWORD',
        'charset' => 'utf8mb4',
    ],

    'app' => [
        'name' => 'Сметограм',
        'url' => 'https://сметограм.рф',
    ],
];
