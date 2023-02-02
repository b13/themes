<?php

if (!defined('TYPO3')) {
    die('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
    'KayStrobach.themes',
    'web', // Main area
    'mod1', // Name of the module
    '', // Position of the module
    [
        // Allowed controller action combinations
        \KayStrobach\Themes\Controller\EditorController::class => 'index,update,saveCategoriesFilterSettings,create', // dropped: showTheme,setTheme,showThemeDetails
    ],
    [
        // Additional configuration
        'access'         => 'user,group',
        'iconIdentifier' => 'module-themes',
        'labels'         => 'LLL:EXT:themes/Resources/Private/Language/locallang.xlf',
    ]
);


// register svg icons: identifier and filename
$iconsSvg = [
    'module-themes' => 'Resources/Public/Icons/Extension.svg',
    'content-button' => 'Resources/Public/Icons/new_content_el_ButtonContent.svg',
    'switch-off' => 'Resources/Public/Icons/power_grey.svg',
    'switch-on' => 'Resources/Public/Icons/power_green.svg',
    'switch-disable' => 'Resources/Public/Icons/power_orange.svg',
    'overlay-theme' => 'Resources/Public/Icons/overlay_theme.svg',
    'contains-theme' => 'Resources/Public/Icons/Extension.svg',
    'new_content_el_buttoncontent' => 'Resources/Public/Icons/new_content_el_ButtonContent.svg',
];
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
foreach ($iconsSvg as $identifier => $path) {
    $iconRegistry->registerIcon(
        $identifier,
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:themes/' . $path]
    );
}
