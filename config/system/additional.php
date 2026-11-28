<?php

// Database connection — all values from environment variables
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driver']   = getenv('TYPO3_DB_DRIVER')   ?: 'mysqli';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host']     = getenv('TYPO3_DB_HOST')     ?: 'localhost';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port']     = (int)(getenv('TYPO3_DB_PORT') ?: 3306);
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname']   = getenv('TYPO3_DB_NAME')     ?: '';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user']     = getenv('TYPO3_DB_USERNAME') ?: '';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] = getenv('TYPO3_DB_PASSWORD') ?: '';

// Encryption key — generate once per environment and store as TYPO3_ENCRYPTION_KEY
$encryptionKey = getenv('TYPO3_ENCRYPTION_KEY');

if ($encryptionKey === false || $encryptionKey === '') {
    throw new RuntimeException('TYPO3_ENCRYPTION_KEY is required');
}

$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $encryptionKey;

// Site base URL — consumed by config/sites/main/config.yaml via %env(TYPO3_SITE_URL)%
$siteUrl = getenv('TYPO3_SITE_URL');

if ($siteUrl === false || $siteUrl === '') {
    throw new RuntimeException('TYPO3_SITE_URL is required (e.g. https://www.example.com/)');
}

// cacheHash — survives typo3 setup rewrites
// Without this, TYPO3 returns 404 for URLs with query params that lack a cHash token.
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['enforceValidation'] = false;

// Log MyVendor classes at DEBUG level — survives typo3 setup rewrites
$GLOBALS['TYPO3_CONF_VARS']['LOG']['MyVendor']['writerConfiguration']['debug']['TYPO3\CMS\Core\Log\Writer\FileWriter'] = [
    'disabled' => false,
    'logFile'  => '/var/www/html/var/log/myvendor.log',
];
