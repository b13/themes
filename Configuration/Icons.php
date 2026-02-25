<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'module-themes' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:themes/Resources/Public/Icons/Extension.svg',
    ],
    'content-button' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:themes/Resources/Public/Icons/new_content_el_ButtonContent.svg',
    ],
    'switch-off' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:themes/Resources/Public/Icons/power_grey.svg',
    ],
    'switch-on' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:themes/Resources/Public/Icons/power_green.svg',
    ],
    'switch-disable' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:themes/Resources/Public/Icons/power_orange.svg',
    ],
    'overlay-theme' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:themes/Resources/Public/Icons/overlay_theme.svg',
    ],
    'contains-theme' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:themes/Resources/Public/Icons/Extension.svg',
    ],
    'new_content_el_buttoncontent' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:themes/Resources/Public/Icons/new_content_el_ButtonContent.svg',
    ],
];
