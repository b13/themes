<?php

declare(strict_types=1);

namespace KayStrobach\Themes\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

class TemplateRepository
{
    public function __construct(private readonly ConnectionPool $connectionPool)
    {}

    public function findByPageId(int $pid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_template');
        $queryBuilder->select('*')
            ->from('sys_template')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT))
            );
        if (!empty($GLOBALS['TCA']['sys_template']['ctrl']['sortby'])) {
            $queryBuilder->orderBy($GLOBALS['TCA']['sys_template']['ctrl']['sortby']);
        }
        $templateRow = $queryBuilder->executeQuery()->fetchAssociative();
        if ($templateRow === false) {
            return null;
        }
        return $templateRow;
    }
}
