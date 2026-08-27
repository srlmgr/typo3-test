<?php

// Database connection — all values from environment variables
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driver']   = getenv('TYPO3_DB_DRIVER')   ?: 'mysqli';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host']     = getenv('TYPO3_DB_HOST')     ?: 'localhost';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port']     = (int)(getenv('TYPO3_DB_PORT') ?: 3306);
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname']   = getenv('TYPO3_DB_NAME')     ?: '';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user']     = getenv('TYPO3_DB_USERNAME') ?: '';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] = getenv('TYPO3_DB_PASSWORD') ?: '';

// Encryption key — generate once per environment and store as TYPO3_ENCRYPTION_KEY
$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = getenv('TYPO3_ENCRYPTION_KEY') ?: '';
