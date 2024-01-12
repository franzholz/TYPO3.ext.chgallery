<?php

defined('TYPO3') || die('Access denied.');

call_user_func(function ($extensionKey, $table): void {        
    $listType = CHGALLERY_EXT . '_pi1';

    $GLOBALS['TCA'][$table]['types']['list']['subtypes_excludelist'][$listType] = 'layout,pages';

    $GLOBALS['TCA'][$table]['types']['list']['subtypes_addlist'][$listType] = 'pi_flexform';

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue($listType, 'FILE:EXT:' . CHGALLERY_EXT . '/flexform_ds.xml');

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
        ['LLL:EXT:' . CHGALLERY_EXT . '/Resources/Private/Language/locallang_db.xlf:tt_content.list_type_pi1', $listType, 'EXT:' . CHGALLERY_EXT . '/Resources/Public/Icons/Extension.gif'],
        'list_type',
        CHGALLERY_EXT
    );
}, 'chgallery', basename(__FILE__, '.php'));
