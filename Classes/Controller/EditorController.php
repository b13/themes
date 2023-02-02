<?php

namespace KayStrobach\Themes\Controller;

/***************************************************************
 *
 * Copyright notice
 *
 * (c) 2019 TYPO3 Themes-Team <team@typo3-themes.org>
 *
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use KayStrobach\Themes\Domain\Repository\TemplateRepository;
use KayStrobach\Themes\Utilities\TsParserUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class EditorController extends ActionController
{
    protected string $extensionName = 'Themes';
    protected int $id = 0;
    protected TemplateRepository $templateRepository;
    protected ?array $template = null;
    protected TsParserUtility $tsParser;
    protected array $externalConfig = [];
    protected array $deniedFields = [];
    protected array $allowedCategories = [];
    protected IconFactory $iconFactory;
    protected ?ModuleTemplate $moduleTemplate;
    protected PageRenderer $pageRenderer;
    protected ModuleTemplateFactory $moduleTemplateFactory;

    public function __construct(
        IconFactory $iconFactory,
        PageRenderer $pageRenderer,
        ModuleTemplateFactory $moduleTemplateFactory,
        TemplateRepository $templateRepository,
        TsParserUtility $tsParser
    ) {
        $this->iconFactory = $iconFactory;
        $this->pageRenderer = $pageRenderer;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->templateRepository = $templateRepository;
        $this->tsParser = $tsParser;
    }

    protected function initializeAction(): void
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->id = (int)GeneralUtility::_GET('id');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Themes/Colorpicker');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Themes/ThemesBackendModule');
        $this->pageRenderer->addCssFile( 'EXT:themes/Resources/Public/Stylesheet/BackendModule.css');
        $this->pageRenderer->addCssFile('EXT:themes/Resources/Public/Contrib/colorpicker/css/colorpicker.css');
        // Try to load the selected template
        $this->template = $this->templateRepository->findByPageId($this->id);
        // Create menu and buttons
        $this->createButtons();
        // Get extension configuration
        $extensionConfiguration = $this->getExtensionConfiguration('themes');
        // Initially, get configuration from extension manager!
        $extensionConfiguration['categoriesToShow'] = GeneralUtility::trimExplode(',', $extensionConfiguration['categoriesToShow']);
        $extensionConfiguration['constantsToHide'] = GeneralUtility::trimExplode(',', $extensionConfiguration['constantsToHide']);
        // mod.tx_themes.constantCategoriesToShow.value
        // Get value from page/user typoscript
        $externalConstantCategoriesToShow = $this->getBackendUser()->getTSConfig(
            'mod.tx_themes.constantCategoriesToShow',
            BackendUtility::getPagesTSconfig($this->id)
        );
        if ($externalConstantCategoriesToShow['value']) {
            $this->externalConfig['constantCategoriesToShow'] = GeneralUtility::trimExplode(',', $externalConstantCategoriesToShow['value']);
            $extensionConfiguration['categoriesToShow'] = array_merge(
                $extensionConfiguration['categoriesToShow'],
                $this->externalConfig['constantCategoriesToShow']
            );
        }
        // mod.tx_themes.constantsToHide.value
        // Get value from page/user typoscript
        $externalConstantsToHide = $this->getBackendUser()->getTSConfig(
            'mod.tx_themes.constantsToHide',
            BackendUtility::getPagesTSconfig($this->id)
        );
        if ($externalConstantsToHide['value']) {
            $this->externalConfig['constantsToHide'] = GeneralUtility::trimExplode(',', $externalConstantsToHide['value']);
            $extensionConfiguration['constantsToHide'] = array_merge(
                $extensionConfiguration['constantsToHide'],
                $this->externalConfig['constantsToHide']
            );
        }
        $this->allowedCategories = $extensionConfiguration['categoriesToShow'];
        $this->deniedFields = $extensionConfiguration['constantsToHide'];
        // initialize normally used values
    }

    public function indexAction(): ResponseInterface
    {
        if ($this->template !== null) {
            $this->view->assign('template', $this->template);
            $this->view->assign('categories', $this->renderFields($this->tsParser, $this->id, $this->allowedCategories, $this->deniedFields));
            $categoriesFilterSettings = $this->getBackendUser()->getModuleData('mod-web_ThemesMod1/Categories/Filter/Settings', 'ses');
            if ($categoriesFilterSettings === null) {
                $categoriesFilterSettings = [];
                $categoriesFilterSettings['searchScope'] = 'all';
            }
            $categoriesFilterSettings['showBasic'] = '1';
            $categoriesFilterSettings['showAdvanced'] = '1';
            $categoriesFilterSettings['showExpert'] = '1';
            $this->view->assign('categoriesFilterSettings', $categoriesFilterSettings);
        }
        $this->view->assign('pid', $this->id);
        return $this->renderResponse();
    }

    public function updateAction(array $data, array $check, int $pid): ResponseInterface
    {
        /*
         * @todo check wether user has access to page BEFORE SAVE!
         */
        $this->tsParser->applyToPid($pid, $data, $check);
        $uri = $this->uriBuilder->reset()->uriFor('index');
        return new RedirectResponse($uri);
    }

    public function createAction()
    {
        if ($this->id > 0 && $this->template === null) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $data = [
                'sys_template' => [
                    StringUtility::getUniqueId('NEW') => [
                        'pid' => $this->id,
                        'title' => 'theme template'
                    ]
                ]
            ];
            $dataHandler->start($data, []);
            $dataHandler->process_datamap();
        }
        $uri = $this->uriBuilder->reset()->uriFor('index');
        return new RedirectResponse($uri);
    }

    public function saveCategoriesFilterSettingsAction(): ResponseInterface
    {
        // Validation definition
        $validSettings = [
            'searchScope'  => 'string',
        ];
        // Validate params
        $categoriesFilterSettings = [];
        foreach ($validSettings as $setting => $type) {
            if ($this->request->hasArgument($setting)) {
                if ($type == 'boolean') {
                    $categoriesFilterSettings[$setting] = (bool) $this->request->getArgument($setting) ? '1' : '0';
                } elseif ($type == 'string') {
                    $categoriesFilterSettings[$setting] = ctype_alpha($this->request->getArgument($setting)) ? $this->request->getArgument($setting) : 'all';
                }
            }
        }
        // Save settings
        $this->getBackendUser()->pushModuleData('mod-web_ThemesMod1/Categories/Filter/Settings', $categoriesFilterSettings);
        //
        // Create JSON-String
        $response = [
            'success' => '',
            'error' => '',
            'data' => $categoriesFilterSettings,
        ];
        return new JsonResponse($response);
    }

    protected function createButtons(): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $buttons = [];
        if ($this->template !== null) {
            $buttons[] = $buttonBar->makeInputButton()
                ->setName('save')
                ->setValue('1')
                ->setForm('saveableForm')
                ->setIcon($this->iconFactory->getIcon('actions-document-save', Icon::SIZE_SMALL))
                ->setTitle('Save');
        }
        foreach ($buttons as $button) {
            $buttonBar->addButton($button, ButtonBar::BUTTON_POSITION_LEFT);
        }
    }

    protected function getExtensionConfiguration(string $extensionKey): array
    {
        /** @var ExtensionConfiguration $extensionConfiguration */
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        /** @var array $configuration */
        $configuration = $extensionConfiguration->get($extensionKey);
        return $configuration;
    }

    protected function renderFields(TsParserUtility $tsParserWrapper, int $pid, array $allowedCategories = null, array $deniedFields = null): array
    {
        $definition = [];
        $categories = $tsParserWrapper->getCategories($pid);
        $subcategories = $tsParserWrapper->getSubCategories($pid);
        $constants = $tsParserWrapper->getConstants($pid);
        foreach ($categories as $categoryName => $category) {
            asort($category);
            if (is_array($category) && (($allowedCategories === null) || (in_array($categoryName, $allowedCategories)))) {
                $title = $GLOBALS['LANG']->sL('LLL:EXT:themes/Resources/Private/Language/Constants/locallang.xml:cat_'.$categoryName);
                if (strlen($title) === 0) {
                    $title = $categoryName;
                }
                $definition[$categoryName] = [
                    'key'   => $categoryName,
                    'title' => $title,
                    'items' => [],
                ];
                foreach (array_keys($category) as $constantName) {
                    if (($deniedFields === null) || (!in_array($constantName, $deniedFields))) {
                        if (isset($subcategories[$constants[$constantName]['subcat_name']][0])) {
                            $constants[$constantName]['subcat_name'] = $subcategories[$constants[$constantName]['subcat_name']][0];
                        }
                        // Basic, advanced or expert?!
                        $constants[$constantName]['userScope'] = 'advanced';
                        if (isset($categories['basic']) && array_key_exists($constants[$constantName]['name'], $categories['basic'])) {
                            $constants[$constantName]['userScope'] = 'basic';
                        } elseif (isset($categories['advanced']) && array_key_exists($constants[$constantName]['name'], $categories['advanced'])) {
                            $constants[$constantName]['userScope'] = 'advanced';
                        } elseif (isset($categories['expert']) && array_key_exists($constants[$constantName]['name'], $categories['expert'])) {
                            $constants[$constantName]['userScope'] = 'expert';
                        }
                        // Only get the first category
                        $catParts = explode(',', $constants[$constantName]['cat']);
                        if (isset($catParts[1])) {
                            $constants[$constantName]['cat'] = $catParts[0];
                        }
                        // Extract sub category
                        $subcatParts = explode('/', $constants[$constantName]['subcat']);
                        if (isset($subcatParts[1])) {
                            $constants[$constantName]['subCategory'] = $subcatParts[1];
                        }
                        $definition[$categoryName]['items'][] = $constants[$constantName];
                    }
                }
            }
        }

        return array_values($definition);
    }

    protected function renderResponse(): ResponseInterface
    {
        $this->moduleTemplate->setContent($this->view->render());
        $html = $this->moduleTemplate->renderContent();
        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
        $response->getBody()->write($html ?? $this->view->render());
        return $response;
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }
}
