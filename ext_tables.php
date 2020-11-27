<?php
defined('TYPO3_MODE') || die('Access denied.');
define('CHGALLERY_EXT', 'chgallery');

call_user_func(function () {

    if (
        TYPO3_MODE == 'BE'
    ) {
        $GLOBALS['TBE_MODULES_EXT']['xMOD_db_new_content_el']['addElClasses']['JambageCom\\Chgallery\\Controller\\Plugin\\WizardIcon'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(CHGALLERY_EXT) . 'Classes/Controller/Plugin/WizardIcon.php';
        
        $moduleName = CHGALLERY_EXT . '_chgalleryM1';
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
            $moduleName,
            '$moduleName',
            '',
            null,
            [
                'access' => 'admin',
                'name' => $moduleName,
                'icon' => 'EXT:' . CHGALLERY_EXT . '/wizard/wizard.gif',
            ]
        );
    }
});

