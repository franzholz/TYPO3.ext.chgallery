<?php
defined('TYPO3_MODE') || die('Access denied.');
defined('TYPO3_version') || die('The constant TYPO3_version is undefined in chgallery!');

call_user_func(function () {
    if (!defined ('CHGALLERY_EXT')) {
        define('CHGALLERY_EXT', 'chgallery');
    }

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43(
        CHGALLERY_EXT,
        'pi1/class.tx_chgallery_pi1.php',
        '_pi1',
        'list_type',
        1
    );

    // here we register "tx_exampleextraevaluations_extraeval1"

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tce']['formevals'][\JambageCom\Chgallery\UserFunc\ExtraEval::class] = '';

    // necessary migration for the FAL uid usage in the ratings extension instead of the former path and filename
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update'][CHGALLERY_EXT . 'RatingsReference'] =
    \JambageCom\Chgallery\Updates\MigrateRatingsReferenceUpdater::class;
});


