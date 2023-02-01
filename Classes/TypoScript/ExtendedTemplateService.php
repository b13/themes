<?php

namespace KayStrobach\Themes\TypoScript;

class ExtendedTemplateService extends \TYPO3\CMS\Core\TypoScript\ExtendedTemplateService
{
    public function getCategories(): array
    {
        return $this->categories;
    }

    public function unsetCategory(string $categoryName, string $constantName): void
    {
        if (isset($this->categories[$categoryName][$constantName])) {
            unset($this->categories[$categoryName][$constantName]);
        }
    }

    public function unsetCategories(string $categoryName): void
    {
        if (isset($this->categories[$categoryName])) {
            unset($this->categories[$categoryName]);
        }
    }
}
