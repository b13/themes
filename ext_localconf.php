<?php

if (!defined('TYPO3')) {
    die('Access denied.');
}

/*
 * Register hook to inject themes
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['KayStrobach\\Themes\\Domain\\Repository\\ThemeRepository']['init'][]
    = \KayStrobach\Themes\Hooks\ThemesDomainRepositoryThemeRepositoryInitHook::class . '->init';

