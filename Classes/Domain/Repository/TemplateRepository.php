<?php

declare(strict_types=1);
namespace KayStrobach\Themes\Domain\Repository;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TemplateRepository implements SingletonInterface
{

    public function findByPageId(int $pid): ?array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_template');
        $queryBuilder->select('*')
            ->from('sys_template')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT))
            );
        if (!empty($GLOBALS['TCA']['sys_template']['ctrl']['sortby'])) {
            $queryBuilder->orderBy($GLOBALS['TCA']['sys_template']['ctrl']['sortby']);
        }
        $templateRow = $queryBuilder->execute()->fetchAssociative();
        if ($templateRow === false) {
            return null;
        }
        return $templateRow;
    }
}
