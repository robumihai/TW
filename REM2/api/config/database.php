<?php
/**
 * REMS - Real Estate Management System
 * Database Configuration - SQLite
 */

declare(strict_types=1);

return [
    'type' => 'sqlite',
    'path' => __DIR__ . '/../../database/rems.sqlite',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
]; 