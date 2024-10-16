<?php

declare(strict_types=1);

return [
    'web_ThemesMod1' => [
        'parent' => 'web',
        'access' => 'user',
        'iconIdentifier' => 'module-themes',
        'labels' => 'LLL:EXT:themes/Resources/Private/Language/locallang.xlf',
        'extensionName' => 'themes',
        'controllerActions' => [
            'KayStrobach\Themes\Controller\EditorController' => [
                'index',
                'update',
                'saveCategoriesFilterSettings',
                'create',
            ],
        ],
    ],
];
