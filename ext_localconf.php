<?php

defined('TYPO3') || die('The constant TYPO3_version is undefined in chgallery!');

call_user_func(function ($extensionKey): void {
    if (!defined('CHGALLERY_EXT')) {
        define('CHGALLERY_EXT', $extensionKey);
    }

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43(
        $extensionKey,
        'pi1/class.tx_chgallery_pi1.php',
        '_pi1',
        'list_type',
        1
    );

    // here we register "tx_exampleextraevaluations_extraeval1"

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tce']['formevals'][\JambageCom\Chgallery\UserFunc\ExtraEval::class] = '';

    // necessary migration for the FAL uid usage in the ratings extension instead of the former path and filename
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update'][$extensionKey . 'RatingsReference'] =
    \JambageCom\Chgallery\Updates\MigrateRatingsReferenceUpdater::class;
}, 'chgallery');
