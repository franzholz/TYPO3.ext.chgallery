<?php
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
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;
use JambageCom\Chgallery\Controller\InitializationController;
use JambageCom\Div2007\Utility\MarkerUtility;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use JambageCom\Chgallery\Api\Api;
use JambageCom\Chgallery\Domain\Composite;


use JambageCom\Chgallery\Utility\FalUtility;

/**
* Plugin 'Simple gallery' for the 'chgallery' extension.
*
* @author	Georg Ringer <http://www.ringer.it/>
* @package	TYPO3
* @subpackage	tx_chgallery
*/
class tx_chgallery_pi1 extends AbstractPlugin
{
    public $prefixId      = 'tx_chgallery_pi1';		// Same as class name
    public $scriptRelPath = 'pi1/class.tx_chgallery_pi1.php';	// Path to this script relative to the extension dir.
    public $extKey        = CHGALLERY_EXT;	// The extension key.
    public $pi_checkCHash = true;


    /**
    * The main method of the PlugIn
    *
    * @param	string		$content: The PlugIn content
    * @param	array		$conf: The PlugIn configuration
    * @return	The content that is displayed on the website
    */
    public function main($content, $conf)
    {
        $composite = $this->init($content, $conf);
        $config = $composite->getConfig();

        // call the correct function to display LIST or single gallery
        if ($config['show'] == 'SINGLE') {
            $content = $this->getSingleView($composite);
        } elseif ($config['show'] == 'LIST') {
            $content = ($this->piVars['dir'] != 0) ? $this->getGalleryView($composite) : $this->getCategoryView($composite);
        } else {
            $content = $this->getGalleryView($composite);
        }

        return $this->pi_wrapInBaseClass($content);
    }

    public function init(&$content, $conf)
    {
        $initialization = GeneralUtility::makeInstance(
            InitializationController::class
        );
        $composite = null;
        $initialization->init(
            $composite,
            $this->piVars,
            $content,
            $conf,
            $this->cObj,
            $this->prefixId
        );

        return $composite;
    }

    /**
    * Get the single image view including all kind of information about the image
    *
    * @return	single image view
    */
    public function getSingleView(Composite $composite)
    {
        $api = GeneralUtility::makeInstance(Api::class);
        $templateService = GeneralUtility::makeInstance(MarkerBasedTemplateService::class);
        $conf = $composite->getConf();
        $config = $composite->getConfig();
        $template['total'] = $templateService->getSubpart($composite->getTemplateCode(), '###TEMPLATE_SINGLE###');
        $tagArray = MarkerUtility::getTags($template['total']);
        $dir = $this->piVars['dir'];
        $singleImage = $this->piVars['single'];

        // return empty string if no get var for the single image
        if ($singleImage == 0) {
            return '';
        }

        // get the single image from CATEGORY view
        if ($dir > 0) {
            // get all dirs
            $dirList 	= $config['subfolders'][$dir - 1];
            $imageList 	= $api->getImagesOfDir($conf['fileTypes'], $dirList['path']);
            $imgPos = ($config['exclude1stImg'] == 0) ? $singleImage - 1 : $singleImage;

            $markerArray = $this->getSingleImageSlice($this->cObj, $tagArray, $imageList, $conf['single.'], $imgPos, $conf['exif'], $conf['RATINGS'], $conf['RATINGS.']);
            // get the single image from GALLERY view
        } else {
            $imageList = $api->getImagesOfDir($conf['fileTypes'], $config['path']); // get all imgs of the dir
            $markerArray = $this->getSingleImageSlice($this->cObj, $tagArray, $imageList, $conf['single.'], $singleImage - 1, $conf['exif'], $conf['RATINGS'], $conf['RATINGS.']);
        }

        // count=0 means, that this is the LIST view which has no image to load, so hide everything
        if(count($markerArray) == 0) {
            $subpartArray['###SINGLE_IMAGE###'] = '';
        }

        // pagebrowser: PREV image
        $linkConf = [];
        $linkConf['parameter'] = $api->getLinkParameter();
        $linkConf['useCacheHash'] = 1;

        if ($singleImage > 1) {
            $override = $this->piVars;
            $override['single'] = $singleImage - 1;

            // check if previous image is on the previous page
            if ($override['single'] / $config['pagebrowser'] <= $this->piVars['pointer']) {
                $override['pointer'] = $override['pointer'] - 1;
                if ($override['pointer'] == 0) {
                    $override['pointer'] = '';
                } // if value 0, set it to '' to avoid showing 0 in the url
            }

            // change param array to string
            foreach ($override as $key => $value) {
                if ($key != '') {
                    $linkConf['additionalParams'] .=  '&tx_chgallery_pi1[' . $key . ']=' . $value;
                }
            }

            $markerArray['###PREV###'] = $this->cObj->typolink($this->pi_getLL('previousImage'), $linkConf);
        } else {
            $markerArray['###PREV###'] = '';
        }

        // pagebrowser: NEXT image
        if ($singleImage < count($imageList)) {
            $override = $this->piVars;
            $override['single'] = $singleImage + 1;

            // check if next image is on the next page
            $pointer = ($this->piVars['pointer'] == 0) ? 1 : $this->piVars['pointer'];
            if (($override['single'] / $config['pagebrowser']) > $pointer) {
                $override['pointer'] = $override['pointer'] + 1;
            }

            // change param array to string
            foreach ($override as $key => $value) {
                if ($key != '') {
                    $linkConf['additionalParams'] .=  '&tx_chgallery_pi1[' . $key . ']=' . $value;
                }
            }

            $markerArray['###NEXT###'] = $this->cObj->typolink($this->pi_getLL('nextImage'), $linkConf);
        } else {
            $markerArray['###NEXT###'] = '';
        }

        // hide exif
        if ($markerArray['###EXIF###'] == '') {
            $subpartArray['###EXIF###'] = '';
        }

        $content .= $templateService->substituteMarkerArrayCached($template['total'], $markerArray, $subpartArray);
        return $content;
    }


    /**
    * Get a list of all subdirs including preview and link to single view
    *
    * @return	The list
    */
    public function getCategoryView(Composite $composite)
    {
        $api = GeneralUtility::makeInstance(Api::class);
        $templateService = GeneralUtility::makeInstance(MarkerBasedTemplateService::class);
        $conf = $composite->getConf();
        $config = $composite->getConfig();
        $languageObj = $api->getLanguageObj();
        $template['total'] = $templateService->getSubpart($composite->getTemplateCode(), '###TEMPLATE_LIST###');
        $template['item'] = $templateService->getSubpart($template['total'], '###ITEM###');

        foreach ($config['subfolders'] as $key => $value) {
            // generall markers
            $markerList = ['size', 'description', 'path', 'title', 'name', 'date'];
            foreach($markerList as $mKey) {
                $markerArray['###LL_' . strtoupper($mKey) . '###'] 	= $languageObj->getLabel($mKey);
            }
            $markerArray['###ZEBRA###'] = ($key % 2 == 0) ? 'odd' : 'even';

            // preview image
            $imgageConf = $conf['category.']['image.'];
            $imgageConf['file'] = $api->getImagesOfDir($conf['fileTypes'], $value['path'], true);
            $markerArray['###IMAGE###'] =  $this->cObj->getContentObject('IMAGE')->render($imgageConf);

            // create the link to the dir
            $linkConf = $conf['category.']['link.'];
            $linkConf['parameter'] = $this->getLinkParameter();


            $linkConf['additionalParams'] = $api->getExtraVars($conf['extraAdditionalParams']) . '&tx_chgallery_pi1[dir]=' . ($key + 1);
            $linkConf['title'] = $value['title'];
            $wrappedSubpartArray['###LINK_ITEM###'] = explode('|', (string) $this->cObj->typolink('|', $linkConf));

            $content_item .= $templateService->substituteMarkerArrayCached($template['item'], $markerArray, $array, $wrappedSubpartArray);
        }

        // put everything into the template
        $subpartArray['###CONTENT###'] = $content_item;
        $content .= $templateService->substituteMarkerArrayCached($template['total'], $markerArray, $subpartArray);
        return $content;
    }


    /**
    * Get a single gallery
    *
    * @return	Whole gallery
    */
    public function getGalleryView(Composite $composite)
    {
        $config = $composite->getConfig();
        $api = GeneralUtility::makeInstance(Api::class);

        // if page browser needs to be used
        if (!isset($this->piVars['pointer'])) {
            $pb = 0 ;
        } else {
            $pb = intval($this->piVars['pointer']);
        }

        // page browser
        $begin 	= $pb * (int) $config['pagebrowser'];
        $end 	= $begin + (int) $config['pagebrowser'];

        $content = $api->getSingleGalleryPage($composite, $pb, $begin, $end);
        return $content;
    }
}
