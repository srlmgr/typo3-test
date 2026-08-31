<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$columns = [
    'tx_sitepackage_external_endpoint' => [
        'label' => 'LLL:EXT:site_package/Resources/Private/Language/locallang_db.xlf:pages.tx_sitepackage_external_endpoint',
        'config' => [
            'type' => 'input',
            'size' => 50,
            'max' => 255,
            'eval' => 'trim',
            'placeholder' => 'https://vrdb-http.mpapenbr.de',
        ],
    ],
    'tx_sitepackage_external_base_path' => [
        'label' => 'LLL:EXT:site_package/Resources/Private/Language/locallang_db.xlf:pages.tx_sitepackage_external_base_path',
        'config' => [
            'type' => 'input',
            'size' => 20,
            'max' => 255,
            'eval' => 'trim',
            'placeholder' => '/vrpc',
        ],
    ],
    'tx_sitepackage_external_season_id' => [
        'label' => 'LLL:EXT:site_package/Resources/Private/Language/locallang_db.xlf:pages.tx_sitepackage_external_season_id',
        'config' => [
            'type' => 'number',
            'size' => 10,
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('pages', $columns);

ExtensionManagementUtility::addFieldsToPalette(
    'pages',
    'external-app',
    'tx_sitepackage_external_endpoint, tx_sitepackage_external_base_path, --linebreak--, tx_sitepackage_external_season_id'
);

$GLOBALS['TCA']['pages']['palettes']['external-app']['label'] = 'LLL:EXT:site_package/Resources/Private/Language/locallang_db.xlf:pages.palette.external_app';

ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    '--div--;LLL:EXT:site_package/Resources/Private/Language/locallang_db.xlf:pages.tab.external_app,--palette--;;external-app',
);
