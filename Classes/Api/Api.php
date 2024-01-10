<?php

namespace JambageCom\Chgallery\Api;

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
/**
*
* API object
*
* @package TYPO3
* @subpackage chgallery
*
*
*/
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use JambageCom\Div2007\Base\BrowserBase;
use JambageCom\Div2007\Utility\MarkerUtility;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;


use JambageCom\Div2007\Utility\BrowserUtility;


use JambageCom\Chgallery\Domain\Composite;
use JambageCom\Chgallery\Utility\FalUtility;

class Api implements SingletonInterface
{
    public $languageObj;
    protected $resourceFactory;
    protected $storageUid;

    public function __construct()
    {
        $storageUid = 0;
        $this->setStorageUid($storageUid);
        $resourceFactory = ResourceFactory::getInstance();
        $this->setResourceFactory($resourceFactory);
    }

    public function setResourceFactory($value)
    {
        $this->resourceFactory = $value;
    }

    public function getResourceFactory()
    {
        return $this->resourceFactory;
    }

    public function setStorageUid($value)
    {
        $this->storageUid = $value;
    }

    public function getStorageUid()
    {
        return $this->storageUid;
    }

    public function setLanguageObj($value)
    {
        $this->languageObj = $value;
    }

    public function getLanguageObj()
    {
        return $this->languageObj;
    }

    /**
    * Get all information about a single image by reading its exif info, description,...
    *
    * @param	string		$path: Path of the image
    * @param	int		$pos: Position of the image in the gallery
    * @param	string		$view: Type of view to use the correct TS (gallery, single,...)
    * @param    array   $conf: shortcut to the TS configuration of the current view
    * @param	int		$count: Count of the images in the dir
    * @return	array Every information about this image filled in markers
    */

    public function getImageMarker(ContentObjectRenderer $cObj, array $tagArray, $path, $pos, $view, $viewConf, $count, $exif = true, $ratingsCObjectType, $ratingsConfig)
    {
        $marker = [];
        $languageObj = $this->getLanguageObj();

        if (!is_file($path)) {
            return $marker;
        }

        // single image TS configuration
        $singleImageConf = $viewConf['image.'];
        $singleImageConf['file'] = $path;
        $description = str_replace('"', '\'', $this->getDescription($path, 'file'));
        $singleImageConf['altText'] = $description;

        // Adds hook for processing of cObj->data to use it via TS later with field = ...
        $data = [];
        $data['Title'] 		= $description;
        $data['File'] 		= $path;
        $data['Filename']	= basename($path);

        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['chgallery']['extraItemDataHook'])) {
            foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['chgallery']['extraItemDataHook'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $data = $_procObj->extraItemDataProcessor($data, $path, $pos, $view, $this);
            }
        }

        foreach($data as $key => $value) {
            $cObj->data['tx_chgallery' . $key]	= $value;
        }

        // fill the markers
        $marker['###IMAGE###'] = $cObj->getContentObject('IMAGE')->render($singleImageConf);
        $marker['###DESCRIPTION###'] = $cObj->stdWrap($description, $viewConf['description.']);
        $marker['###DOWNLOAD###'] = $cObj->filelink($path, $viewConf['download.']);
        $marker['###FILENAME###'] = $cObj->stdWrap(basename($path), $viewConf['file.']);
        $marker['###POSITION###'] = $cObj->stdWrap($pos + 1, $viewConf['position.']);
        $marker['###COUNT###'] = $cObj->stdWrap($count, $viewConf['count.']);

        // load information from exif
        if ($exif) {
            $exif_array = @exif_read_data($path, true, false); // Load all EXIF informations from the original Pic in an Array
            $marker['###EXIF###'] = '1';
            $marker['###EXIF_SIZE###'] =  $cObj->stdWrap($exif_array['FileSize'], $viewConf['exif_size.']);
            $marker['###EXIF_TIME###'] =  $cObj->stdWrap($exif_array['FileDateTime'], $viewConf['exif_time.']);
        } else {
            $marker['###EXIF###'] = '';
        }

        // language markers
        $tmpValues = ['description', 'download', 'exif_size', 'exif_time', 'filename'];
        foreach($tmpValues as $key) {
            $marker['###LL_' . strtoupper($key) . '###'] = $languageObj->getLabel($key);
        }

        // neu FHO Anfang +++
        $extKey = '';
        $api = '';

        // check need for ratings
        if (
            (
                $tagArray['RATINGS'] || $tagArray['RATINGS_STATIC']
            ) &&
            $ratingsCObjectType != '' && isset($ratingsConfig) && is_array($ratingsConfig)
        ) {
            $extKey = $ratingsConfig['extkey'];
            $api = $ratingsConfig['api'];
        }

        if (
            $extKey != '' &&
            ExtensionManagementUtility::isLoaded($extKey) &&
            $api != '' &&
            class_exists($api)
        ) {
            $apiObj = GeneralUtility::makeInstance($api);
            if (method_exists($apiObj, 'getDefaultConfig')) {
                $ratingsConf = $apiObj->getDefaultConfig();
                if (isset($ratingsConf) && is_array($ratingsConf)) {
                    $tmpConf = $ratingsConfig;
                    ArrayUtility::mergeRecursiveWithOverrule($tmpConf, $ratingsConf);
                    $ratingsConf = $tmpConf;
                } else {
                    $ratingsConf = $ratingsConfig;
                }
            } else {
                $ratingsConf = $ratingsConfig;
            }

            $fileName = (strlen($path) < 244) ? $path : substr($path, -240);
            $resourceFactory = $this->getResourceFactory();
            $file = $resourceFactory->getObjectFromCombinedIdentifier($this->getStorageUid() . ':' . $fileName);
            $ratingsConf['ref'] = CHGALLERY_EXT . '_' . $file->getUid(); // hier +++ die uid aus der FAL eintragen
            $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            /* @var $cObj \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer */
            $cObj->start([]);

            $marker['###RATINGS###'] = $cObj->cObjGetSingle($ratingsCObjectType, $ratingsConf);
            $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            /* @var $cObj \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer */
            $cObj->start([]);
            $ratingsConf['mode'] = 'static';
            $marker['###RATINGS_STATIC###'] =
                $cObj->cObjGetSingle(
                    $ratingsCObjectType,
                    $ratingsConf
                );
        } else {
            $marker['###RATINGS###'] = '';
            $marker['###RATINGS_STATIC###'] = '';
        }

        // Adds hook for processing of extra item markers
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['chgallery']['extraItemMarkerHook'])) {
            foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['chgallery']['extraItemMarkerHook'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $marker = $_procObj->extraItemMarkerProcessor($marker, $path, $pos, $view, $this);
            }
        }

        return $marker;
    }

    /**
    * Get all images of a directory or just the 1st
    *
    * @param	string		$path: Path of the dir
    * @param	boolean		$firstOnly: If true, return the 1st image
    * @return	array/text of images
    */
    public function getImagesOfDir($fileTypes, $path, $firstOnly = false)
    {
        $imageList = GeneralUtility::getFilesInDir($path, $fileTypes, 1, 1);
        if ($firstOnly) {
            return array_shift($imageList);
        }

        return $imageList;
    }

    /**
    * Get the correct single image out of the correct directory by knowing its position in the dir
    *
    * @param	array		$imageList: List of images of the dir
    * @param	int		$pos: Position of the img which is needed
    * @return	array Every information about this image filled in markers
    */
    public function getSingleImageSlice(ContentObjectRenderer $cObj, array $tagArray, $viewConf, $imageList, $pos, $exif, $ratingsCObjectType, $ratingsConfig)
    {
        $finalImage = array_slice($imageList, $pos, 1); // get the only image
        $finalImage = array_values($finalImage); // get the value=path
        $view = 'single';
        $marker =
            $this->getImageMarker(
                $cObj,
                $tagArray,
                $finalImage[0],
                $pos,
                $view,
                $viewConf,
                count($imageList),
                $exif,
                $ratingsCObjectType,
                $ratingsConfig
            );
        return $marker;
    }

    /**
    * Get the content of a txt file which serves as description
    * for directories and files
    *
    * @param	string		$path: Path of the dir
    * @param	string		$type: Type of txt
    * @return	the description
    */
    public function getDescription($path, $type = '')
    {
        $multilingual = ($GLOBALS['TSFE']->sys_language_uid > 0) ? '-' . $GLOBALS['TSFE']->sys_language_uid : '';

        if ($type == 'dir') { // description of a directory
            $file = $path . 'info' . $multilingual . '.txt';
        } else {	// description of a file
            $file = $path.$multilingual . '.txt';
        }

        if (is_file($file)) {
            $text = file_get_contents($file);
        }
        return $text;
    }

    /**
    * Get the correct parameter for the links
    *
    * @return either the ID or the anchor
    */
    public function getLinkParameter()
    {
        $link = '';
        if ($this->conf['useAnchor'] == 1) {
            $link = ' #c' . $this->cObj->data['uid'];
        } else {
            $link = $GLOBALS['TSFE']->id;
        }

        return $link;
    }

    /**
    * Render empty links to images from an image array
    *
    * @param	array  $imgList: Array with the images
    * @return all links next to each other
    */
    public function getRenderAllLinks(
        ContentObjectRenderer $cObj,
        array $imageConf,
        $imgList
    ) {
        $links = '';
        foreach ($imgList as $key => $singleImage) {

            $cObj->data['tx_chgalleryImageLong'] = $singleImage['file'];
            $imageConf['altText'] = str_replace('"', '\'', $this->getDescription($singleImage['file'], 'file'));
            $cObj->data['tx_chgalleryTitle'] = $imageConf['altText'];
            $links .= $cObj->typolink(' ', $imageConf);
        }
        return $links;
    }

    /**
    * Get the internal page browser
    *
    * @param	arryay  $marker: existing markers
    * @param	int  $count: Maximum count of elements
    * @param	int  $limit: how many elements displayed
    * @return markerarray with the pagebrowser
    */
    public function getPageBrowserMarkers($composite, $browserConf, $marker, $count, $limit)
    {
        $browseObj =
            $this->getBrowserObj(
                $composite->getConf(),
                $browserConf,
                $count,
                $composite->getPiVars(),
                $limit,
                1000
            );

        $marker['###PAGEBROWSER###'] =
            BrowserUtility::render(
                $browseObj,
                $this->getLanguageObj(),
                $composite->getCObj(),
                $composite->getPrefixId(),
                true,
                1,
                '',
                $browserConf,
                $pointerName,
                true,
                $addQueryString
            );
        $marker['###PAGEBROWSERTEXT###'] =
            BrowserUtility::render(
                $browseObj,
                $this->getLanguageObj(),
                $composite->getCObj(),
                $composite->getPrefixId(),
                true,
                2,
                '',
                $browserConf,
                $pointerName,
                false,
                $addQueryString
            );

        return $marker;
    }

    public function getBrowserObj(
        $conf,
        $browserConf,
        $recordCount,
        $piVars,
        $limit,
        $maxPages
    ) {
        $bShowFirstLast = true;

        if (
            isset($browserConf) &&
            is_array($browserConf) &&
            isset($browserConf['showFirstLast'])
        ) {
            $bShowFirstLast = $browserConf['showFirstLast'];
        }
        $pagefloat = 'center';
        $maxPages = 18;
        $showFirstLast = 0;
        $imageArray = [];
        $imageActiveArray = [];
        $browseObj = GeneralUtility::makeInstance(BrowserBase::class);
        $browseObj->init(
            $conf,
            $piVars,
            [],
            false,  // no autocache used yet
            false, // USER obj
            $recordCount,
            $limit,
            $maxPages,
            $showFirstLast,
            false,
            $pagefloat,
            $imageArray,
            $imageActiveArray
        );
        $browseObj->internal['dontLinkActivePage'] = 0;
        $browseObj->internal['showRange'] = 0;

        return $browseObj;
    }

    /**
    * Random view of an array and slice it afterwards, preserving the keys
    *
    * @param	array  $array: Array to modify
    * @param	array  $offset: Where to start the slicing
    * @param	array  $length: Length of the sliced array
    * @return the randomized and sliced array
    */
    public function getSlicedRandomArray($array, $offset, $length)
    {
        // shuffle
        $new_arr = [];
        while (count($array) > 0) {
            $val = array_rand($array);
            $new_arr[$val] = $array[$val];
            unset($array[$val]);
        }
        $result = $new_arr;

        // slice
        $result2 = [];
        $i = 0;
        if($offset < 0) {
            $offset = count($result) + $offset;
        }
        if($length > 0) {
            $endOffset = $offset + $length;
        } elseif($length < 0) {
            $endOffset = count($result) + $length;
        } else {
            $endOffset = count($result);
        }

        // collect elements
        foreach($result as $key => $value) {
            if($i >= $offset && $i < $endOffset) {
                $result2['random'][$key] = $value;
            } else {
                $result2['after'][$key] = $value;
            }

            $i++;
        }
        return $result2;

    }

    /**
    * Check the path for a secure and valid one
    *
    * @param	string		$path: Path which is checked
    * @return	string	valid path
    */
    public function checkPath($path)
    {
        $path = trim($path);

        $path = FalUtility::convertFalPath($path);

        if (!GeneralUtility::validPathStr($path)) {
            return '';
        }

        if (substr($path, -1) != '/') { // check for needed / at the end
            $path =  $path . '/';
        }

        if (substr($path, 0, 1) == '/') { // check for / at the beginning
            $path = substr($path, 1, strlen($path));
        }

        return $path;
    }


    /**
    * Helper function to sort directories by date
    *
    * @param	array		$dirs: categories
    * @param	string		$sort: sorting direction
    * @return	correct sorting
    */
    public function sortByDate(&$dirs, $sort)
    {
        if($sort == 'dateasc') {
            usort($dirs, [&$this, 'dateASC']);
        } elseif($sort == 'datedesc') {
            usort($dirs, [&$this, 'dateDESC']);
        }
    }


    /**
    * Helper function to sort directories ascending
    *
    * @param	int		$a: date 1
    * @param	int		$b: date 3
    * @return	correct sorting
    */
    public function dateASC($a, $b)
    {
        return ($a['date'] < $b['date']) ? -1 : 1;
    }


    /**
    * Helper function to sort directories descending
    *
    * @param	int		$a: date 1
    * @param	int		$b: date 3
    * @return	correct sorting
    */
    public function dateDESC($a, $b)
    {
        return ($a['date'] > $b['date']) ? -1 : 1;
    }


    public function getExtraVars($extraAdditionalParams)
    {
        $vars = '';
        // add extra get vars to the links
        if ($extraAdditionalParams) {
            $tmpList = GeneralUtility::trimExplode(',', $extraAdditionalParams);
            foreach($tmpList as $key) {
                if (
                    is_array(GeneralUtility::_GET($key)) &&
                    count(GeneralUtility::_GET($key)) > 0
                ) {
                    $vars .= GeneralUtility::implodeArrayForUrl($key, GeneralUtility::_GET($key));
                }
            }
        }
        return $vars;
    }


    /**
    * Get a gallery page
    *
    * @param	int		$pb: Pointer of the pagebrowser
    * @param	int		$begin: Begin of the pagebrowser
    * @param	int		$end: End of the pagebrowser
    * @param	boolean		$ajax: If ajax is used
    * @return	array/text of images
    */
    public function getSingleGalleryPage(Composite $composite, $pb, $begin, $end, $ajax = 0)
    {
        $templateService = GeneralUtility::makeInstance(MarkerBasedTemplateService::class);
        // templates
        $ajaxTemplateSuffix = ($ajax == 1) ? '_AJAX' : '';
        $cObj = $composite->getCObj();
        $conf = $composite->getConf();
        $config = $composite->getConfig();
        $piVars = $composite->getPiVars();
        $languageObj = $this->getLanguageObj();
        $template['total'] = $templateService->getSubpart($composite->getTemplateCode(), '###TEMPLATE' . $ajaxTemplateSuffix . '###');
        $template['item'] = $templateService->getSubpart($template['total'], '###ITEM###');
        $tagArray = MarkerUtility::getTags($template['item']);
        $markerArray = [];

        // get all infos we need
        // if LIST view, get the information about the category
        if ($config['show'] == 'LIST' && $piVars['dir'] != 0) {
            $dirKey = $piVars['dir'];
            $linkToDir = '&tx_chgallery_pi1[dir]=' . $dirKey;
            $dirKey--;
            $subDir = $config['subfolders'][$dirKey];
            $path = $subDir['path'];

            foreach ($subDir as $key => $value) {
                $markerArray['###DIR_' . strtoupper($key) . '###'] = $cObj->stdWrap($subDir[$key], $conf['gallery.']['dir_' . $key . '.']);
                $markerArray['###LL_' . strtoupper($key) . '###']  = $languageObj->getLabel($key);
            }

            $backLink = [];
            $backLink['parameter'] = $GLOBALS['TSFE']->id;
            $markerArray['###DIR_BACK###'] = $cObj->typolink($languageObj->getLabel('dir_back'), $backLink);
        } else {
            $path = $config['path'];

            // hide the subdir part
            $subpartArray['###SUBDIR_NAVIGATION###'] = '';
        }

        // get all images of the dir
        $imageList = GeneralUtility::getFilesInDir($path, $conf['fileTypes'], 1, 1);

        // exclude 1st image if set and if this is a detail view of LIST
        if ($config['exclude1stImg'] == 1 && $config['show'] == 'LIST' && $piVars['dir'] != 0) {
            $firstEl = array_shift($imageList);
        }

        // error check
        if (count($imageList) == 0) {
            return '';
        }

        // create the page browser and the links
        $count 	= count($imageList);
        $totalPages = ceil($count / $config['pagebrowser']);
        $browserConf = [
            'browseBoxWrap' 	=> '|',
            'showResultsWrap' => '<span class="result">|</span>',
            'browseLinksWrap' => '<span class="links">|</span>',
            'showResultsNumbersWrap' => '|',
            'disabledLinkWrap' => '|',
            'inactiveLinkWrap' => '|',
            'activeLinkWrap' => '|'
        ];

        // get the markers of the pagebrowser
        $markerArray = $this->getPageBrowserMarkers($composite, $browserConf, $markerArray, count($imageList), $config['pagebrowser']);

        $linkToDir .= $this->getExtraVars($conf['extraAdditionalParams']);

        // if image of the single view should be passed through the page browsers link
        $singleImage = $piVars['single'];
        if ($singleImage > 0 && $conf['single.']['pass'] == 1) {
            $linkToDir .= '&tx_chgallery_pi1[single]=' . $singleImage;
        }

        // create the links for the pagebrowser
        $linkConf = $conf['link.'];
        $linkConf['parameter'] = $this->getLinkParameter();
        $linkConf['additionalParams'] = $linkToDir;

        // first
        $linkConf['title'] = $languageObj->getLabel('pi_list_browseresults_first');
        $markerArray['###FIRST###'] = $cObj->typolink($languageObj->getLabel('pi_list_browseresults_first'), $linkConf);
        // last
        $linkConf['title'] = $languageObj->getLabel('pi_list_browseresults_last');
        $linkConf['additionalParams'] = $linkToDir . '&tx_chgallery_pi1[pointer]=' . ($totalPages - 1);
        $markerArray['###LAST###'] = $cObj->typolink($languageObj->getLabel('pi_list_browseresults_last'), $linkConf);

        // next
        if ($pb + 1 < $totalPages) {
            $linkConf['title'] = $languageObj->getLabel('pi_list_browseresults_next');
            $linkConf['additionalParams'] = $linkToDir . '&tx_chgallery_pi1[pointer]=' . ($pb + 1);
            $markerArray['###NEXT###'] = $cObj->typolink($languageObj->getLabel('pi_list_browseresults_next'), $linkConf);
        } else {
            $markerArray['###NEXT###'] = '';
        }

        // prev
        $linkConf['title'] = $languageObj->getLabel('pi_list_browseresults_prev');
        if ($pb > 1) {
            $linkConf['additionalParams'] = $linkToDir . '&tx_chgallery_pi1[pointer]=' . ($pb - 1);
            $markerArray['###PREV###'] = $cObj->typolink($languageObj->getLabel('pi_list_browseresults_prev'), $linkConf);
        } elseif ($pb == 1) {
            $linkConf['additionalParams'] = $linkToDir . '';
            $markerArray['###PREV###'] = $cObj->typolink($languageObj->getLabel('pi_list_browseresults_prev'), $linkConf);
        } elseif ($conf['ajax'] == 1) {
            $linkConf['additionalParams'] = $linkToDir . '';
            $linkConf['ATagParams'] = 'class="hide"';
            $markerArray['###PREV###'] = '&nbsp;' . $cObj->typolink($languageObj->getLabel('pi_list_browseresults_prev'), $linkConf);
        } else {
            $markerArray['###PREV###'] = '&nbsp;';
        }

        // max used pages
        $markerArray['###PAGEBROWSERPAGES###'] = $totalPages;

        // ajax url
        $actionConf = [];
        #		$actionConf['parameter'] = $GLOBALS['TSFE']->id;
        #		$actionConf['additionalParams'] = $linkToDir.'&type=9712';
        $actionConf['parameter'] = $GLOBALS['TSFE']->id . ',9712';
        $actionConf['returnLast'] = 'url';
        $markerArray['###AJAXURL###'] = $cObj->typolink('', $actionConf);
        // include ajax script
        $markerArray['###AJAXSCRIPT###'] = '<script  src="' . $GLOBALS['TSFE']->tmpl->getFileName($conf['ajaxScript']) . '" type="text/javascript"></script>';

        $markerArray['###LINKSBEFORE###'] = '';
        $markerArray['###LINKSAFTER###'] = '';

        // merge image + description to be able to sort them and not loosing the relation between them
        $allList = [];
        $j = 0;
        foreach ($imageList as $key => $value) {
            $allList[$j]['file'] = $value;
            $j++;
        }

        // Random mode, get a randomized array
        // Use plugin.tx_chgallery_pi1 = USER_INT !
        if ($config['random'] == 1) {

            // hide the subdir part
            $subpartArray['###PAGEBROWSER###'] = '';

            $randomImageList = $this->getSlicedRandomArray($allList, $begin, $config['pagebrowser']);
            $newImageList = $randomImageList['random'];

            // if all links should be renderd, all other links are after the existing images and
            // need to be taken from the same function because of the randomizing
            if ($config['renderAllLinks'] == 1) {
                $markerArray['###LINKSAFTER###'] =
                    $this->getRenderAllLinks(
                        $conf['gallery.']['renderAllLinks.'],
                        $randomImageList['after']
                    );
            }
        } else {
            // just get the elements we need
            $newImageList = array_slice($allList, $begin, $config['pagebrowser']);
        }

        // config of the single image & check for usage of link
        $imageConf = $conf['gallery.']['image.'];
        if ($config['link'] != '') {
            $imageConf['stdWrap.']['typolink.'] = $conf['link.'];
            $imageConf['stdWrap.']['typolink.']['parameter'] = $config['link'];
            unset($imageConf['imageLinkWrap']);
        }

        // render the link before/after the current page
        // if random ==1, the links are rendered some lines before
        if ($config['renderAllLinks'] == 1 && $config['random'] != 1) {
            // previous images, from 0 to begin
            $prevImgList = array_slice($allList, 0, $begin);

            $markerArray['###LINKSBEFORE###'] .=
                $this->getRenderAllLinks(
                    $cObj,
                    $conf['gallery.']['renderAllLinks.'],
                    $prevImgList
                );

            // after images, from current page + number of images at this page to the end
            $beginForAfterImg = ($begin + $config['pagebrowser']);
            $endForAfterImg = ($count - $beginForAfterImg);

            $afterImgList = array_slice($allList, $beginForAfterImg, $endForAfterImg);
            $markerArray['###LINKSAFTER###'] .=
                $this->getRenderAllLinks(
                    $cObj,
                    $conf['gallery.']['renderAllLinks.'],
                    $afterImgList
                );
        }

        // create the gallery
        foreach ($newImageList as $key => $singleImage) {
            // if single view, render a different link
            if ($config['single'] == 1 && $config['link'] == '') {
                $id = ($key + 1) + $begin;
                $imageConf['stdWrap.']['typolink.']['additionalParams'] = '&tx_chgallery_pi1[single]=' . ($id);
                if ($piVars['dir'] > 0) {
                    $imageConf['stdWrap.']['typolink.']['additionalParams'] .=  '&tx_chgallery_pi1[dir]=' . $piVars['dir'];
                }

                if ($pb > 0) {
                    $imageConf['stdWrap.']['typolink.']['additionalParams'] .=  '&tx_chgallery_pi1[pointer]=' . $pb;
                }
                $imageConf['stdWrap.']['typolink.']['parameter'] = $GLOBALS['TSFE']->id;
                unset($imageConf['imageLinkWrap']);
            }

            $imageConf['file'] = $singleImage['file'];
            $description = str_replace('"', '\'', $this->getDescription($singleImage['file'], 'file'));
            $imageConf['altText'] = $description;

            $view = 'gallery';
            $markerArrayImage =
                $this->getImageMarker(
                    $cObj,
                    $tagArray,
                    $singleImage['file'],
                    $key + 1,
                    $view,
                    $conf[$view . '.'],
                    $count,
                    $conf['exif'],
                    $conf['RATINGS'],
                    $conf['RATINGS.']
                );

            $markerArrayImage['###IMAGE###'] =
                $cObj->getContentObject('IMAGE')->render($imageConf);

            // hide exif
            if ($markerArrayImage['###EXIF###'] == '') {
                $subpartArray['###EXIF###'] = '';
            }

            // get the current image
            $currentImgId = ($piVars['pointer'] * $config['pagebrowser']) + $key + 1 ;
            if ($currentImgId == $piVars['single'] && $piVars['single'] > 1) {
                $markerArrayImage['###ACT###'] = ' act';
            } else {
                $markerArrayImage['###ACT###'] = '';
            }

            $content_item .=
                $templateService->substituteMarkerArrayCached(
                    $template['item'],
                    $markerArrayImage,
                    $subpartArray
                );
        }

        // put everything into the template
        $subpartArray['###CONTENT###'] = $content_item;

        // Adds hook for processing of extra item markers
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['chgallery']['extraGalleryPageMarkerHook'])) {
            foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['chgallery']['extraGalleryPageMarkerHook'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $markerArray = $_procObj->extraItemMarkerProcessor($markerArray, $path, $pb, $this);
            }
        }

        $content .=
            $templateService->substituteMarkerArrayCached(
                $template['total'],
                $markerArray,
                $subpartArray
            );

        return $content;
    }
}
