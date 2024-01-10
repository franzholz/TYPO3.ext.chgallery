<?php

namespace JambageCom\Chgallery\Updates;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with TYPO3 source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use Symfony\Component\Console\Output\OutputInterface;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

use TYPO3\CMS\Core\Utility\GeneralUtility;

use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\ConfirmableInterface;
use TYPO3\CMS\Install\Updates\Confirmation;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use TYPO3\CMS\Install\Service\UpgradeWizardsService;

/**
 * Migrate Ratings Reference
 *
 * Before version 3.0.0 chgallery use the path name in the reference field of the database tables tx_ratings_data and tx_ratings_iplog:
 *
 *  chgalleryfileadmin/path/filename.jpg.
 *
 * Starting with version 3.0.0 the file references are stored with the extension name and the uid of the file object returned by the ResourceFactory method getObjectFromCombinedIdentifier.
 *
 *  sys_file_uid
 *
 * where uid comes from the sys_file record where the identifier and name match the path name of the file.
 * If you do not execute this update script, this has the effect that all your ratings to the images will not work any more.
 *
 */

class MigrateRatingsReferenceUpdater implements UpgradeWizardInterface, ConfirmableInterface, ChattyInterface
{
    final public const TABLE_NAMES = '"tx_ratings_data" and "tx_ratings_iplog"';
    final public const TABLES = 'tx_ratings_data,tx_ratings_iplog';

    /**
    * @var OutputInterface
    */
    protected $output;

    /**
     * @var string
     */
    protected $title = 'EXT:' . CHGALLERY_EXT . ' - Migrate rating references';

    /**
     * @var string
     */
    protected $identifier = CHGALLERY_EXT . 'RatingsReference';


    /**
     * Setter injection for output into upgrade wizards
     */
    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle(): string
    {
        return 'Migrate ratings references from path to FAL uid after updating to version 3.0.0 of ' . CHGALLERY_EXT . ' .';
    }

    /**
     * Get description
     *
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'Migrate the ratings tables references used by ' . CHGALLERY_EXT . ' into a FAL unique id without the path and filename. The tables ' . static::TABLE_NAMES . ' use these reference strings to relate the ratings to your images. This shall be executed once if you update from a former version to 3.0.0 or higher';
    }

    /**
     * @return string Unique identifier of this updater
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Return a confirmation message instance
     *
     * @return \TYPO3\CMS\Install\Updates\Confirmation
     */
    public function getConfirmation(): Confirmation
    {
        $message = '';
        $elementCount = $this->getUpdatableReferencesCount();

        if ($elementCount) {
            $message = sprintf('%s ratings records with references containing path and filenames can possibly be migrated.', $elementCount);
        } else {
            $message = 'No ratings records records can be migrated';
        }
        $title = 'Migration of rating records with references to ' . CHGALLERY_EXT . ' related images to use FAL uids instead of filenames and paths.';
        $confirm = 'Yes, please migrate now!';
        $deny = 'No';
        $result = GeneralUtility::makeInstance(
            Confirmation::class,
            $title,
            $message,
            false,
            $confirm,
            $deny,
            $elementCount > 0
        );

        return $result;
    }

    /**
     * Performs the accordant updates.
     *
     * @param array &$dbQueries Queries done in this update
     * @param string|array &$customMessages Custom messages
     * @return bool Whether everything went smoothly or not
     */
    public function performUpdate(array &$dbQueries, &$customMessages): bool
    {
        $dbQueries = [];
        $customMessages = [];
        $storageUid = 0;
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $tables = explode(',', (string) static::TABLES);

        foreach ($tables as $k => $table) {
            $count = 0;
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
            $queryBuilder->getRestrictions()->removeAll();
            $statement = $queryBuilder
                ->select('*')
                ->from($table)->where($queryBuilder->expr()->like(
                'reference',
                $queryBuilder->createNamedParameter(CHGALLERY_EXT . 'fileadmin%')
            ))->executeQuery();

            while (($row = $statement->fetch()) && $count >= 0) { // use a $count limitation for development purposes
                $fileName = substr((string) $row['reference'], strlen((string) CHGALLERY_EXT));
                $updateRow = [];
                try {
                    $count++;
                    $file = $resourceFactory->getObjectFromCombinedIdentifier($storageUid . ':' . $fileName);
                    $updateRow['reference'] = CHGALLERY_EXT . '_' . $file->getUid();
                } catch (ResourceDoesNotExistException) {
                    // Not found
                    if ($k == 0) {
                        $customMessages[] = 'file not found: "' . $fileName . '"';
                    }
                    $updateRow['deleted'] = '1';
                }

                $updateBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getQueryBuilderForTable($table);
                $updateStatement = $updateBuilder
                    ->update($table)
                    ->where(
                        $updateBuilder->expr()->eq(
                            'uid',
                            $updateBuilder->createNamedParameter(
                                $row['uid'],
                                \PDO::PARAM_INT
                            )
                        )
                    );
                foreach ($updateRow as $field => $value) {
                    $updateStatement->set($field, $value);
                }
                $dbQueries[] = $updateStatement->getSQL();
                $updateStatement->execute();
            }
            $customMessages[] = $count . ' records of the table "' . $table . '" have been successfully converted.';
        }

        return true;
    }

    /**
     * Returns rating records having path and filenames instead of FAL uids
     *
     * @return count of found rows
     */
    protected function getUpdatableReferencesCount(): int
    {
        $result = 0;
        $tables = explode(',', (string) static::TABLES);

        foreach ($tables as $table) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
            $queryBuilder->getRestrictions()->removeAll();
            $query = $queryBuilder
                ->count('*')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->like(
                        'reference',
                        $queryBuilder->createNamedParameter(CHGALLERY_EXT . 'fileadmin%')
                    )
                );
            $result += $query->execute()
                ->fetchColumn(0);
        }

        return $result;
    }

    /**
     * Execute the update
     * Called when a wizard reports that an update is necessary
     *
     * @return bool
     */
    public function executeUpdate(): bool
    {
        $queries = [];
        $message = '';
        $result = $this->performUpdate($queries, $message);
        $this->output->write($message);
        return $result;
    }

    /**
     * Is an update necessary?
     * Is used to determine whether a wizard needs to be run.
     * Check if data for migration exists.
     *
     * @return bool
     */
    public function updateNecessary(): bool
    {
        $elementCount = $this->getUpdatableReferencesCount();
        return ($elementCount > 0);
    }

    /**
     * Returns an array of class names of Prerequisite classes
     * This way a wizard can define dependencies like "database up-to-date" or
     * "reference index updated"
     *
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class
        ];
    }
}
