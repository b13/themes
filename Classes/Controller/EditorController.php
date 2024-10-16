<?php

declare(strict_types=1);

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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\TypoScript\AST\AstBuilderInterface;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\AST\Traverser\AstTraverser;
use TYPO3\CMS\Core\TypoScript\AST\Visitor\AstConstantCommentVisitor;
use TYPO3\CMS\Core\TypoScript\IncludeTree\SysTemplateRepository;
use TYPO3\CMS\Core\TypoScript\IncludeTree\SysTemplateTreeBuilder;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Traverser\IncludeTreeTraverser;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Visitor\IncludeTreeCommentAwareAstBuilderVisitor;
use TYPO3\CMS\Core\TypoScript\Tokenizer\LosslessTokenizer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class EditorController extends ActionController
{
    protected string $extensionName = 'Themes';
    protected int $id = 0;
    protected ?array $template = null;
    protected array $deniedFields = [];
    protected array $allowedCategories = [];
    protected ?ModuleTemplate $moduleTemplate;

    public function __construct(
        protected IconFactory $iconFactory,
        protected PageRenderer $pageRenderer,
        protected ModuleTemplateFactory $moduleTemplateFactory,
        protected TemplateRepository $templateRepository,
        private readonly SysTemplateRepository $sysTemplateRepository,
        private readonly SysTemplateTreeBuilder $treeBuilder,
        private readonly IncludeTreeTraverser $treeTraverser,
        private readonly AstTraverser $astTraverser,
        private readonly AstBuilderInterface $astBuilder,
        private readonly LosslessTokenizer $losslessTokenizer,
    ) {}

    protected function initializeAction(): void
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->id = (int)$this->request->getQueryParams()['id'];
        $this->pageRenderer->loadJavaScriptModule('@kaystrobach/themes/Colorpicker.js');
        $this->pageRenderer->loadJavaScriptModule('@kaystrobach/themes/ThemesBackendModule.js');
        $this->pageRenderer->addCssFile('EXT:themes/Resources/Public/Stylesheet/BackendModule.css');
        $this->pageRenderer->addCssFile('EXT:themes/Resources/Public/Contrib/colorpicker/css/colorpicker.css');
        // Try to load the selected template
        $this->template = $this->templateRepository->findByPageId($this->id);
        // Create menu and buttons
        $this->createButtons();
        // Get extension configuration
        $extensionConfiguration = $this->getExtensionConfiguration('themes');
        // Initially, get configuration from extension manager!
        $this->allowedCategories = GeneralUtility::trimExplode(',', $extensionConfiguration['categoriesToShow']);
        $this->deniedFields = GeneralUtility::trimExplode(',', $extensionConfiguration['constantsToHide']);
        // initialize normally used values
    }

    public function indexAction(): ResponseInterface
    {
        if ($this->template !== null) {
            $this->moduleTemplate->assign('template', $this->template);

            $selectedTemplateUid = $this->template['uid'];
            $pageUid = $this->id;
            $request = $this->request;
            $currentTemplateConstants = $this->template['constants'] ?? '';

            $rootLine = GeneralUtility::makeInstance(RootlineUtility::class, $pageUid)->get();
            $sysTemplateRows = $this->sysTemplateRepository->getSysTemplateRowsByRootlineWithUidOverride($rootLine, $request, $selectedTemplateUid);
            $site = $request->getAttribute('site');
            $constantIncludeTree = $this->treeBuilder->getTreeBySysTemplateRowsAndSite('constants', $sysTemplateRows, $this->losslessTokenizer, $site);
            $constantAstBuilderVisitor = GeneralUtility::makeInstance(IncludeTreeCommentAwareAstBuilderVisitor::class);
            $this->treeTraverser->traverse($constantIncludeTree, [$constantAstBuilderVisitor]);
            $constantAst = $constantAstBuilderVisitor->getAst();
            $astConstantCommentVisitor = GeneralUtility::makeInstance(AstConstantCommentVisitor::class);
            $currentTemplateFlatConstants = $this->astBuilder->build($this->losslessTokenizer->tokenize($currentTemplateConstants), new RootNode())->flatten();
            $astConstantCommentVisitor->setCurrentTemplateFlatConstants($currentTemplateFlatConstants);
            $this->astTraverser->traverse($constantAst, [$astConstantCommentVisitor]);

            $constants = $astConstantCommentVisitor->getConstants();
            $allCategories = $astConstantCommentVisitor->getCategories();

            $categories = [];
            foreach (array_keys($allCategories) as $category) {
                $arr = GeneralUtility::trimExplode(',', $category);
                $categoryName = $arr[0];
                if (in_array($categoryName, $this->allowedCategories, true) && ($arr[1] ?? '') === 'basic') {
                    $items = [];
                    foreach ($constants as $name => $config) {
                        if (str_starts_with($name, 'themes.configuration.' . $categoryName)) {
                            $items[] = $this->constantConfigForView($config, $categoryName);
                        }
                    }
                    $categories[] = [
                        'key' => $categoryName,
                        'title' => $categoryName,
                        'items' => $items,
                    ];
                }
            }
            $this->moduleTemplate->assign('categories', $categories);
            $categoriesFilterSettings = $this->getBackendUser()->getModuleData('mod-web_ThemesMod1/Categories/Filter/Settings', 'ses');
            if ($categoriesFilterSettings === null) {
                $categoriesFilterSettings = [];
                $categoriesFilterSettings['searchScope'] = 'all';
            }
            $categoriesFilterSettings['showBasic'] = '1';
            $categoriesFilterSettings['showAdvanced'] = '1';
            $categoriesFilterSettings['showExpert'] = '1';
            $this->moduleTemplate->assign('categoriesFilterSettings', $categoriesFilterSettings);
        }
        $this->moduleTemplate->assign('pid', $this->id);
        return $this->moduleTemplate->renderResponse('Index');
    }

    public function updateAction(array $data, array $check, int $pid): ResponseInterface
    {
        /*
         * @todo check wether user has access to page BEFORE SAVE!
         */

        $selectedTemplateUid = $this->template['uid'];
        $pageUid = $this->id;
        $request = $this->request;
        $templateRow = $this->template;

        $rootLine = GeneralUtility::makeInstance(RootlineUtility::class, $pageUid)->get();
        $site = $request->getAttribute('site');
        $sysTemplateRows = $this->sysTemplateRepository->getSysTemplateRowsByRootlineWithUidOverride($rootLine, $request, $selectedTemplateUid);
        $constantIncludeTree = $this->treeBuilder->getTreeBySysTemplateRowsAndSite('constants', $sysTemplateRows, $this->losslessTokenizer, $site);
        $constantAstBuilderVisitor = GeneralUtility::makeInstance(IncludeTreeCommentAwareAstBuilderVisitor::class);
        $this->treeTraverser->traverse($constantIncludeTree, [$constantAstBuilderVisitor]);
        $constantAst = $constantAstBuilderVisitor->getAst();
        $astConstantCommentVisitor = GeneralUtility::makeInstance(AstConstantCommentVisitor::class);
        $this->astTraverser->traverse($constantAst, [$astConstantCommentVisitor]);

        $constants = $astConstantCommentVisitor->getConstants();
        $updatedTemplateConstantsArray = $this->updateTemplateConstants($request, $constants, $templateRow['constants'] ?? '');
        if ($updatedTemplateConstantsArray) {
            $templateUid = $templateRow['uid'];
            $recordData = [];
            $recordData['sys_template'][$templateUid]['constants'] = implode(LF, $updatedTemplateConstantsArray);
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);

            $user = clone $GLOBALS['BE_USER'];
            $user->user['admin'] = 1;
            $dataHandler->start($recordData, [], $user);
            $dataHandler->process_datamap();
        }

        $uri = $this->uriBuilder->reset()->uriFor('index');
        return new RedirectResponse($uri);
    }

    public function createAction(): ResponseInterface
    {
        if ($this->id > 0 && $this->template === null) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $data = [
                'sys_template' => [
                    StringUtility::getUniqueId('NEW') => [
                        'pid' => $this->id,
                        'title' => 'theme template',
                    ],
                ],
            ];
            $user = clone $GLOBALS['BE_USER'];
            $user->user['admin'] = 1;
            $dataHandler->start($data, [], $user);
            $dataHandler->process_datamap();
            $dataHandler->clear_cacheCmd('pages');
            unset($user);
        }
        $uri = $this->uriBuilder->reset()->uriFor('index');
        return new RedirectResponse($uri);
    }

    public function saveCategoriesFilterSettingsAction(string $searchScope): ResponseInterface
    {

        $categoriesFilterSettings = ['searchScope' => $searchScope];

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

    protected function constantConfigForView(array $config, string $categoryName): array
    {
        $constantConfigForView = [
            'cat' => $categoryName,
            'label' => $config['label'],
            'name' => $config['name'],
            'isDefault' => (bool)$config['isInCurrentTemplate'] === false,
            'value' => $config['value'],
            'default_value' => $config['default_value'],
            'subcat_name' => $config['subcat_name'],
            'subCategory' => $config['subcat_name'],
            'userScope' => 'basic',
            'labelValueArray' => $config['labelValueArray'] ?? [],
        ];
        if ($config['type'] === 'int+') {
            $constantConfigForView['typeCleaned'] = 'Int';
        } elseif (substr($config['type'], 0, 3) === 'int') {
            $constantConfigForView['typeCleaned'] = 'Int';
            $constantConfigForView['range'] = substr($config['type'], 3);
        } elseif ($config['type'] === 'small') {
            $constantConfigForView['typeCleaned'] = 'Text';
        } elseif ($config['type'] === 'color') {
            $constantConfigForView['typeCleaned'] = 'Color';
        } elseif ($config['type'] === 'boolean') {
            $constantConfigForView['typeCleaned'] = 'Boolean';
        } elseif ($config['type'] === 'string') {
            $constantConfigForView['typeCleaned'] = 'String';
        } elseif (substr($config['type'], 0, 4) === 'file') {
            $constantConfigForView['typeCleaned'] = 'File';
        } elseif (substr($config['type'], 0, 7) === 'options') {
            $constantConfigForView['typeCleaned'] = 'Options';
            $options = explode(',', substr($config['type'], 8, -1));
            $constantConfigForView['options'] = [];
            foreach ($options as $option) {
                $t = explode('=', $option);
                if (count($t) === 2) {
                    $constantConfigForView['options'][$t[1]] = $t[0];
                } else {
                    $constantConfigForView['options'][$t[0]] = $t[0];
                }
            }
        } elseif ($config['type'] === '') {
            $constantConfigForView['typeCleaned'] = 'String';
        } else {
            $constantConfigForView['typeCleaned'] = 'Fallback';
        }
        return $constantConfigForView;
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

    /** -------------------------- copied from ConstantsEditorController -------------------------------------------- */
    private function updateTemplateConstants(ServerRequestInterface $request, array $constantDefinitions, string $rawTemplateConstants): ?array
    {
        $rawTemplateConstantsArray = explode(LF, $rawTemplateConstants);
        $constantPositions = $this->calculateConstantPositions($rawTemplateConstantsArray);

        $parsedBody = $request->getParsedBody();
        $data = $parsedBody['data'] ?? null;
        $check = $parsedBody['check'] ?? [];

        $valuesHaveChanged = false;
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (!isset($constantDefinitions[$key])) {
                    // Ignore if there is no constant definition for this constant key
                    continue;
                }
                if (!isset($check[$key]) || ($check[$key] !== 'checked' && isset($constantPositions[$key]))) {
                    // Remove value if the checkbox is not set, indicating "value to be dropped from template"
                    $rawTemplateConstantsArray = $this->removeValueFromConstantsArray($rawTemplateConstantsArray, $constantPositions, $key);
                    $valuesHaveChanged = true;
                    continue;
                }
                if ($check[$key] !== 'checked') {
                    // Don't process if this value is not set
                    continue;
                }
                $constantDefinition = $constantDefinitions[$key];
                switch ($constantDefinition['type']) {
                    case 'int':
                        $min = $constantDefinition['typeIntMin'] ?? PHP_INT_MIN;
                        $max = $constantDefinition['typeIntMax'] ?? PHP_INT_MAX;
                        $value = (string)MathUtility::forceIntegerInRange((int)$value, (int)$min, (int)$max);
                        break;
                    case 'int+':
                        $min = $constantDefinition['typeIntMin'] ?? 0;
                        $max = $constantDefinition['typeIntMax'] ?? PHP_INT_MAX;
                        $value = (string)MathUtility::forceIntegerInRange((int)$value, (int)$min, (int)$max);
                        break;
                    case 'color':
                        $col = [];
                        if ($value) {
                            $value = preg_replace('/[^A-Fa-f0-9]*/', '', $value) ?? '';
                            $useFulHex = strlen($value) > 3;
                            $col[] = (int)hexdec($value[0]);
                            $col[] = (int)hexdec($value[1]);
                            $col[] = (int)hexdec($value[2]);
                            if ($useFulHex) {
                                $col[] = (int)hexdec($value[3]);
                                $col[] = (int)hexdec($value[4]);
                                $col[] = (int)hexdec($value[5]);
                            }
                            $value = substr('0' . dechex($col[0]), -1) . substr('0' . dechex($col[1]), -1) . substr('0' . dechex($col[2]), -1);
                            if ($useFulHex) {
                                $value .= substr('0' . dechex($col[3]), -1) . substr('0' . dechex($col[4]), -1) . substr('0' . dechex($col[5]), -1);
                            }
                            $value = '#' . strtoupper($value);
                        }
                        break;
                    case 'comment':
                        if ($value) {
                            $value = '';
                        } else {
                            $value = '#';
                        }
                        break;
                    case 'wrap':
                        if (($data[$key]['left'] ?? false) || $data[$key]['right']) {
                            $value = $data[$key]['left'] . '|' . $data[$key]['right'];
                        } else {
                            $value = '';
                        }
                        break;
                    case 'offset':
                        $value = rtrim(implode(',', $value), ',');
                        if (trim($value, ',') === '') {
                            $value = '';
                        }
                        break;
                    case 'boolean':
                        if ($value) {
                            $value = ($constantDefinition['trueValue'] ?? false) ?: '1';
                        }
                        break;
                }
                if ((string)($constantDefinition['value'] ?? '') !== (string)$value) {
                    // Put value in, if changed.
                    $rawTemplateConstantsArray = $this->addOrUpdateValueInConstantsArray($rawTemplateConstantsArray, $constantPositions, $key, $value);
                    $valuesHaveChanged = true;
                }
            }
        }
        if ($valuesHaveChanged) {
            return $rawTemplateConstantsArray;
        }
        return null;
    }

    private function calculateConstantPositions(
        array $rawTemplateConstantsArray,
        array &$constantPositions = [],
        string $prefix = '',
        int $braceLevel = 0,
        int &$lineCounter = 0
    ): array {
        while (isset($rawTemplateConstantsArray[$lineCounter])) {
            $line = ltrim($rawTemplateConstantsArray[$lineCounter]);
            $lineCounter++;
            if (!$line || $line[0] === '[') {
                // Ignore empty lines and conditions
                continue;
            }
            if (strcspn($line, '}#/') !== 0) {
                $operatorPosition = strcspn($line, ' {=<');
                $key = substr($line, 0, $operatorPosition);
                $line = ltrim(substr($line, $operatorPosition));
                if ($line[0] === '=') {
                    $constantPositions[$prefix . $key] = $lineCounter - 1;
                } elseif ($line[0] === '{') {
                    $braceLevel++;
                    $this->calculateConstantPositions($rawTemplateConstantsArray, $constantPositions, $prefix . $key . '.', $braceLevel, $lineCounter);
                }
            } elseif ($line[0] === '}') {
                $braceLevel--;
                if ($braceLevel < 0) {
                    $braceLevel = 0;
                } else {
                    // Leaving this brace level: Force return to caller recursion
                    break;
                }
            }
        }
        return $constantPositions;
    }

    /**
     * Update a constant value in current template constants if key exists already,
     * or add key/value at the end if it does not exist yet.
     */
    private function addOrUpdateValueInConstantsArray(array $templateConstantsArray, array $constantPositions, string $constantKey, string $value): array
    {
        $theValue = ' ' . trim($value);
        if (isset($constantPositions[$constantKey])) {
            $lineNum = $constantPositions[$constantKey];
            $parts = explode('=', $templateConstantsArray[$lineNum], 2);
            if (count($parts) === 2) {
                $parts[1] = $theValue;
            }
            $templateConstantsArray[$lineNum] = implode('=', $parts);
        } else {
            $templateConstantsArray[] = $constantKey . ' =' . $theValue;
        }
        return $templateConstantsArray;
    }

    /**
     * Remove a key from constant array.
     */
    private function removeValueFromConstantsArray(array $templateConstantsArray, array $constantPositions, string $constantKey): array
    {
        if (isset($constantPositions[$constantKey])) {
            $lineNum = $constantPositions[$constantKey];
            unset($templateConstantsArray[$lineNum]);
        }
        return $templateConstantsArray;
    }
}
