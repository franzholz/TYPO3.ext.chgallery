<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(function () {

    $table = 'tt_content';

    $listType = CHGALLERY_EXT . '_pi1';

    $GLOBALS['TCA'][$table]['types']['list']['subtypes_excludelist'][$listType]='layout,pages';

    $GLOBALS['TCA'][$table]['types']['list']['subtypes_addlist'][$listType] = 'pi_flexform';    
    
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue($listType, 'FILE:EXT:' . CHGALLERY_EXT . '/flexform_ds.xml');
    
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
        array(
            'LLL:EXT:' . CHGALLERY_EXT . '/locallang_db.xml:tt_content.list_type_pi1',
            $listType,
            'EXT:' . CHGALLERY_EXT . '/ext_icon.gif'
        ),
        'list_type',
        CHGALLERY_EXT
    );
});
