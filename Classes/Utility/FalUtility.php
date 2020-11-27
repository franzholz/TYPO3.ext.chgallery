<?php

namespace JambageCom\Chgallery\Utility;

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


class FalUtility {

	/**
	 * If the given path is a FAL path and the storage is local, then the basepath is appended to the path
	 * so it can be used with general file functions in this extension.
	 *
	 * @param $path
	 * @return string
	 */
	public static function convertFalPath($path) {
		if (preg_match('/^file:(\d+):(.*)$/', $path, $matches)) {
			/** @var $storageRepository \TYPO3\CMS\Core\Resource\StorageRepository */
			$storageRepository = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\StorageRepository');
			/** @var $storage \TYPO3\CMS\Core\Resource\ResourceStorage */
			$storage = $storageRepository->findByUid(intval($matches[1]));
			$storageRecord = $storage->getStorageRecord();
			$storageConfiguration = $storage->getConfiguration();
			if ($storageRecord['driver'] === 'Local') {
				$basePath = rtrim($storageConfiguration['basePath'], '/') . '/';
				$path = $basePath . substr($matches[2], 1);
			}
		}
		return $path;
	}
}

