<?php

namespace JambageCom\Chgallery\Controller;

/*
* This file is part of the TYPO3 CMS project.
*
* It is free software; you can redistribute it and/or modify it under
* the terms of the GNU General Public License, either version 2
* of the License, or any later version.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*
* The TYPO3 project - inspiring people to share!
*/
use TYPO3\CMS\Core\SingletonInterface;
use JambageCom\Chgallery\Api\Localization;
use JambageCom\Div2007\Utility\FlexformUtility;
use JambageCom\Chgallery\Api\Api;


/**
*
* initialization method
*
* TypoScript config:
* - See static_template 'plugin.tt_board_tree' and plugin.tt_board_list
* - See TS_ref.pdf
*
* @author	Kasper Skårhøj  <kasperYYYY@typo3.com>
* @author	Franz Holzinger <franz@ttproducts.de>
*/

use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

use JambageCom\Div2007\Utility\ControlUtility;

use JambageCom\Chgallery\Domain\Composite;

class InitializationController implements SingletonInterface
{
    /**
    * does the initialization stuff
    *
    * @param		Composite	  A composite object will be returned.
    * @param		string		  content string
    * @param		string		  configuration array
    * @return	  boolean  false in error case, true if successfull
    */
    public function init(
        &$composite,
        array &$piVars,
        &$content,
        array &$conf,
        ContentObjectRenderer $cObj,
        $prefixId
    ) {
        if (!ExtensionManagementUtility::isLoaded('div2007')) {
            $content = 'Error in Chgallery Extension(' . CHGALLERY_EXT . '): Extension div2007 has not been loaded.';
            return false;
        }

        $config = [];
        $composite = GeneralUtility::makeInstance(Composite::class);

        // *************************************
        // *** getting configuration values:
        // *************************************
        $composite->setPrefixId($prefixId);

        $languageObj = GeneralUtility::makeInstance(Localization::class);
        $languageObj->init(
            CHGALLERY_EXT,
            $conf['_LOCAL_LANG.'],
            'pi1'
        );

        $languageObj->loadLocalLang(
            'EXT:' . CHGALLERY_EXT . '/pi1/locallang.xml',
            false
        );
        $api = GeneralUtility::makeInstance(Api::class);
        $api->setLanguageObj($languageObj);

        ControlUtility::setPiVarDefaults(
            $piVars,
            $cObj,
            $conf
        ); // Set default piVars from TS

        $cObj->data['pi_flexform'] =
            GeneralUtility::xml2array($cObj->data['pi_flexform']);

        // security check, pivars only need integers
        foreach($piVars as $key => $value) {
            $piVars[$key] = intval($value);
        }

        // add the flexform values
        $config['show']			= strtoupper($this->getFlexform($cObj, $conf, '', 'show', 'mode'));
        $config['path']		 	= $api->checkPath($this->getFlexform($cObj, $conf, '', 'path', 'path'));
        $config['description'] 	= $this->getFlexform($cObj, $conf, '', 'description', 'description');
        $config['pagebrowser'] 	= $this->getFlexform($cObj, $conf, '', 'pagebrowser', 'pagebrowser');
        $config['random'] 		= ($this->getFlexform($cObj, $conf, '', 'random', 'random') && $config['show'] == 'GALLERY') ? 1 : 0;
        $config['listTitle']	= $this->getFlexform($cObj, $conf, '', 'title', 'title');
        $config['single']		= $this->getFlexform($cObj, $conf, '', 'single', 'single');
        $config['exclude1stImg']	= (intval($this->getFlexform($cObj, $conf, 'more', 'excludeFirstImage', 'gallery.excludeFirstImage'))) ? 1 : 0;
        $config['categoryOrder'] = $this->getFlexform($cObj, $conf, '', 'categoryOrder', 'categoryOrder');
        $config['categoryOrderAscDesc'] = $this->getFlexform($cObj, $conf, '', 'categoryAscDesc', 'categoryOrderAscDesc');

        // additional options
        $config['renderAllLinks'] = intval($this->getFlexform($cObj, $conf, 'more', 'renderAllLinks', 'gallery.renderAllLinks'));
        $config['link'] 		  = $this->getFlexform($cObj, $conf, 'more', 'link', 'link');

        // create an array of subfolders
        $config['subfolders'] = $this->getFullDir($conf['fileTypes'], $config['path'], $config);

        // Template+  CSS file
        $template = ($this->getFlexform($cObj, $conf, 'more', 'templateFile')) ? 'uploads/tx_chgallery/' . $this->getFlexform($cObj, $conf, 'more', 'templateFile') : $conf['templateFile'];
        $absoluteFileName = $GLOBALS['TSFE']->tmpl->getFileName($template);
        $templateCode = file_get_contents($absoluteFileName);
        $composite->setTemplateCode($templateCode);

        if (isset($conf['pathToCSS']) && $conf['pathToCSS'] != '') {
            $pathToCSS = $GLOBALS['TSFE']->tmpl->getFileName($conf['pathToCSS']);
            if ($pathToCSS != '') {
                $GLOBALS['TSFE']->additionalHeaderData['chgallery_css'] = '<link rel="stylesheet" href="' . $pathToCSS . '" type="text/css" />';
            }
        }

        // Ajax used? Embed js
        if ($conf['ajax'] == 1) {
            $GLOBALS['TSFE']->additionalHeaderData['chgallery'] .= $this->getPath($conf['pathToMootools']) ? '<script src="' . $GLOBALS['TSFE']->tmpl->getFileName($conf['pathToMootools']) . '" type="text/javascript"></script>' : '';
        }

        if ($conf['exif'] == 1 && !extension_loaded('exif')) {
            $conf['exif'] = 0;
        }

        $composite->setConfig($config);
        $composite->setConf($conf);
        $composite->setCObj($cObj);
        $composite->setPiVars($piVars);

    }

    /**
    * Get the value out of the flexforms and if empty, take if from TS
    *
    * @param	string		$sheet: The sheed of the flexforms
    * @param	string		$key: the name of the flexform field
    * @param	string		$confOverride: The value of TS for an override
    * @return	string	The value of the locallang.xml
    */
    public function getFlexform($cObj, $conf, $sheet, $key, $confOverride = '')
    {
        // Default sheet is sDEF
        $sheet = ($sheet == '') ? $sheet = 'sDEF' : $sheet;
        $flexform =
            FlexformUtility::get(
                $cObj->data['pi_flexform'],
                $key,
                $sheet
            );

        // possible override through TS
        if ($confOverride == '') {
            return $flexform;
        } else {
            // hack to work with multiple TS arrays
            $tsparts = explode('.', $confOverride);
            if (count($tsparts) == 1) { // default with no .
                $value = $flexform ?: $conf[$confOverride];
                $value = $cObj->stdWrap($value, $conf[$confOverride . '.']);
            } elseif (count($tsparts) == 2) { // 1 sub array
                $value = $flexform ?: $conf[$tsparts[0] . '.'][$tsparts[1]];
                $value = $cObj->stdWrap($value, $conf[$tsparts[0] . '.'][$tsparts[1] . '.']);
            }

            return $value;
        }
    }

    /**
    * Get all subdirectories of a dir including information about the content
    * Only dirs with images in it are taken
    *
    * @param	string		$path: Path of the dir
    * @return	array with the images, the dir title/descriptin
    */
    public function getFullDir($fileTypes, $path, array $config)
    {
        $api = GeneralUtility::makeInstance(Api::class);
        $dir = GeneralUtility::get_dirs($path);
        $newdir = [];
        $titleList = explode(chr(10), $config['listTitle']);
        $i = 0;

        if(is_array($dir) && !empty($dir)) {

            // sort directories in ascending order to assure appropriate category title and description assignment
            array_multisort($dir, SORT_ASC, SORT_STRING);

            foreach ($dir as $key => $value) {

                $size = $api->getImagesOfDir($fileTypes, $path . $value . '/');

                // if exclude is set, empty means one image
                $empty = ($config['exclude1stImg'] == 1) ? 1 : 0;

                // check if there are images in it

                if (count($size) <= $empty) {
                    unset($dir[$key]);
                } else {
                    $newdir[$key]['path']				= $path . $value . '/';
                    $newdir[$key]['size'] 			= ($config['exclude1stImg'] == 1) ? count($size) - 1 : count($size);
                    $newdir[$key]['title'] 			= $titleList[$i];
                    $newdir[$key]['description'] = $this->getDescription($path . $value . '/', 'dir');
                    $newdir[$key]['name'] 			= $value;
                    $newdir[$key]['date'] 			= filemtime($path . $value);
                    $i++;
                }
            }

            // sorting of categories
            $sort_arr = [];
            foreach($newdir as $uniqid => $row) {
                foreach($row as $key => $value) {
                    $sort_arr[$key][$uniqid] = $value;
                }
            }

            $sort = ($config['categoryOrderAscDesc'] == 'asc') ? SORT_ASC : SORT_DESC;

            // check for old settings
            if (array_key_exists($config['categoryOrder'], ['asc' => 1, 'desc' => 1, 'dateasc' => 1, 'datedesc' => 1])) {
                $config['categoryOrder'] = 'path';
            }

            array_multisort($sort_arr[$config['categoryOrder']], $sort, $newdir);
        }

        return $newdir;
    }

}
