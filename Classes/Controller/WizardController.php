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
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Backend\Template\DocumentTemplate;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;


use JambageCom\Chgallery\Utility\FalUtility;

/**
* chgallery module tx_chgallery_image_aassawiz0
*
* @author    Georg Ringer <g.ringer@cyberhouse.at>
* @package    TYPO3
* @subpackage    tx_chgallery
*/


class WizardController
{
    /**
    * Loaded with the global array $MCONF which holds some module configuration from the conf.php file of backend modules.
    *
    * @see init()
    * @var array
    */
    public $MCONF = [];

    /**
    * The integer value of the GET/POST var, 'id'. Used for submodules to the 'Web' module (page id)
    *
    * @see init()
    * @var int
    */
    public $id;

    /**
    * The value of GET/POST var, 'CMD'
    *
    * @see init()
    * @var mixed
    */
    public $CMD;

    /**
    * A WHERE clause for selection records from the pages table based on read-permissions of the current backend user.
    *
    * @see init()
    * @var string
    */
    public $perms_clause;

    /**
    * The module menu items array. Each key represents a key for which values can range between the items in the array of that key.
    *
    * @see init()
    * @var array
    */
    public $MOD_MENU = [
        'function' => []
    ];

    /**
    * Current settings for the keys of the MOD_MENU array
    *
    * @see $MOD_MENU
    * @var array
    */
    public $MOD_SETTINGS = [];

    /**
    * Module TSconfig based on PAGE TSconfig / USER TSconfig
    *
    * @see menuConfig()
    * @var array
    */
    public $modTSconfig;

    /**
    * If type is 'ses' then the data is stored as session-lasting data. This means that it'll be wiped out the next time the user logs in.
    * Can be set from extension classes of this class before the init() function is called.
    *
    * @see menuConfig(), \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData()
    * @var string
    */
    public $modMenu_type = '';

    /**
    * dontValidateList can be used to list variables that should not be checked if their value is found in the MOD_MENU array. Used for dynamically generated menus.
    * Can be set from extension classes of this class before the init() function is called.
    *
    * @see menuConfig(), \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData()
    * @var string
    */
    public $modMenu_dontValidateList = '';

    /**
    * List of default values from $MOD_MENU to set in the output array (only if the values from MOD_MENU are not arrays)
    * Can be set from extension classes of this class before the init() function is called.
    *
    * @see menuConfig(), \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData()
    * @var string
    */
    public $modMenu_setDefaultList = '';

    /**
    * Contains module configuration parts from TBE_MODULES_EXT if found
    *
    * @see handleExternalFunctionValue()
    * @var array
    */
    public $extClassConf;

    /**
    * Generally used for accumulating the output content of backend modules
    *
    * @var string
    */
    public $content = '';

    /**
    * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
    */
    public $doc;

    /**
    * May contain an instance of a 'Function menu module' which connects to this backend module.
    *
    * @see checkExtObj()
    * @var AbstractFunctionModule
    */
    public $extObj;

    /**
    * @var PageRenderer
    */
    protected $pageRenderer = null;

    /**
     * Indicates if a <div>-output section is open
     *
     * @var int
     * @internal will be removed in TYPO3 v9
     */
    public $sectionFlag = 0;

    public function __construct()
    {
        $this->getLanguageService()->includeLLFile('EXT:' . CHGALLERY_EXT . '/wizard/locallang.xml');
        $this->getBackendUser()->modAccess($GLOBALS['MCONF']);
    }

    /**
    * Initializes the backend module by setting internal variables, initializing the menu.
    *
    * @see menuConfig()
    */
    public function init(): void
    {
        // Name might be set from outside
        if (!$this->MCONF['name']) {
            $this->MCONF = $GLOBALS['MCONF'];
        }
        $this->id = (int)GeneralUtility::_GP('id');
        $this->CMD = GeneralUtility::_GP('CMD');
        $this->perms_clause = $this->getBackendUser()->getPagePermsClause(1);
        $this->menuConfig();
        $this->handleExternalFunctionValue();
    }

    /**
    * Initializes the internal MOD_MENU array setting and unsetting items based on various conditions. It also merges in external menu items from the global array TBE_MODULES_EXT (see mergeExternalItems())
    * Then MOD_SETTINGS array is cleaned up (see \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData()) so it contains only valid values. It's also updated with any SET[] values submitted.
    * Also loads the modTSconfig internal variable.
    *
    * @see init(), $MOD_MENU, $MOD_SETTINGS, \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData(), mergeExternalItems()
    */
    protected function menuConfig()
    {
        $this->MOD_MENU = ['function' => ['1' => $this->getLanguageService()->getLL('function1')]];

        // Page/be_user TSconfig settings and blinding of menu-items
        $this->modTSconfig = BackendUtility::getModTSconfig($this->id, 'mod.' . $this->MCONF['name']);
        $this->MOD_MENU['function'] = $this->mergeExternalItems($this->MCONF['name'], 'function', $this->MOD_MENU['function']);
        $this->MOD_MENU['function'] = BackendUtility::unsetMenuItems($this->modTSconfig['properties'], $this->MOD_MENU['function'], 'menu.function');
        $this->MOD_SETTINGS =
            BackendUtility::getModuleData(
                $this->MOD_MENU,
                GeneralUtility::_GP('SET'),
                $this->MCONF['name'],
                $this->modMenu_type,
                $this->modMenu_dontValidateList,
                $this->modMenu_setDefaultList
            );
    }

    /**
    * Merges menu items from global array $TBE_MODULES_EXT
    *
    * @param string $modName Module name for which to find value
    * @param string $menuKey Menu key, eg. 'function' for the function menu.
    * @param array $menuArr The part of a MOD_MENU array to work on.
    * @return array Modified array part.
    * @access private
    * @see \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(), menuConfig()
    */
    protected function mergeExternalItems($modName, $menuKey, $menuArr)
    {
        $mergeArray = $GLOBALS['TBE_MODULES_EXT'][$modName]['MOD_MENU'][$menuKey];
        if (is_array($mergeArray)) {
            foreach ($mergeArray as $k => $v) {
                if (((string)$v['ws'] === '' || $this->getBackendUser()->workspace === 0 && GeneralUtility::inList($v['ws'], 'online')) || $this->getBackendUser()->workspace === -1 && GeneralUtility::inList($v['ws'], 'offline') || $this->getBackendUser()->workspace > 0 && GeneralUtility::inList($v['ws'], 'custom')) {
                    $menuArr[$k] = $this->getLanguageService()->sL($v['title']);
                }
            }
        }
        return $menuArr;
    }

    /**
    * Loads $this->extClassConf with the configuration for the CURRENT function of the menu.
    *
    * @param string $MM_key The key to MOD_MENU for which to fetch configuration. 'function' is default since it is first and foremost used to get information per "extension object" (I think that is what its called)
    * @param string $MS_value The value-key to fetch from the config array. If NULL (default) MOD_SETTINGS[$MM_key] will be used. This is useful if you want to force another function than the one defined in MOD_SETTINGS[function]. Call this in init() function of your Script Class: handleExternalFunctionValue('function', $forcedSubModKey)
    * @see getExternalItemConfig(), init()
    */
    protected function handleExternalFunctionValue($MM_key = 'function', $MS_value = null)
    {
        if ($MS_value === null) {
            $MS_value = $this->MOD_SETTINGS[$MM_key];
        }
        $this->extClassConf = $this->getExternalItemConfig($this->MCONF['name'], $MM_key, $MS_value);
    }

    /**
    * Returns configuration values from the global variable $TBE_MODULES_EXT for the module given.
    * For example if the module is named "web_info" and the "function" key ($menuKey) of MOD_SETTINGS is "stat" ($value) then you will have the values of $TBE_MODULES_EXT['webinfo']['MOD_MENU']['function']['stat'] returned.
    *
    * @param string $modName Module name
    * @param string $menuKey Menu key, eg. "function" for the function menu. See $this->MOD_MENU
    * @param string $value Optionally the value-key to fetch from the array that would otherwise have been returned if this value was not set. Look source...
    * @return mixed The value from the TBE_MODULES_EXT array.
    * @see handleExternalFunctionValue()
    */
    protected function getExternalItemConfig($modName, $menuKey, $value = '')
    {
        if (isset($GLOBALS['TBE_MODULES_EXT'][$modName])) {
            return (string)$value !== '' ? $GLOBALS['TBE_MODULES_EXT'][$modName]['MOD_MENU'][$menuKey][$value] : $GLOBALS['TBE_MODULES_EXT'][$modName]['MOD_MENU'][$menuKey];
        }
        return null;
    }

    /**
     * Returns the Language Service
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    /**
    * Returns the Backend User
    * @return BackendUserAuthentication
    */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

    /**
    * @return PageRenderer
    */
    protected function getPageRenderer()
    {
        if ($this->pageRenderer === null) {
            $this->pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        }

        return $this->pageRenderer;
    }

    /**
    * Main function of the wizard: Displays all images of a dir
    *
    * @return  string the wizards content
    */
    public function moduleContent(): void
    {
        $vars = GeneralUtility::_GET('P');

        // error checks
        $error = [];
        // check if CE has been saved once!
        if (intval($vars['uid']) == 0) {
            $error[] = $this->getLanguageService()->getLL('error-neversavesd');
        } else {
            $tableName = 'tt_content';
            $queryBuilder = $this->getQueryBuilder($tableName);
            $queryBuilder->setRestrictions(
                GeneralUtility::makeInstance(
                    FrontendRestrictionContainer::class
                )
            );
            // get the single record
            $rows = $queryBuilder
                ->select('uid', 'sys_language_uid', 'pi_flexform')
                ->from($tableName)
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter(
                            intval($vars['uid']),
                            \PDO::PARAM_INT
                        )
                    )
                )->setMaxResults(1)->executeQuery()
                ->fetchAll();

            if (is_array($rows) && !empty($rows)) {
                $row = $rows['0'];
            }

            // get a lanuage prefix for the description
            $this->languagePrefix = ($row['sys_language_uid'] > 0) ? '-'.$row['sys_language_uid'] : '';

            // read the flexform settings and transform it to array
            $flexformArray = GeneralUtility::xml2array($row['pi_flexform']);
            $flexformArray = $flexformArray['data']['sDEF']['lDEF'];

            // get all the infos we need
            $path 					= $this->checkPath(trim((string) $flexformArray['path']['vDEF']));
            $pagebrowser 			= $flexformArray['pagebrowser']['vDEF'];
            $show				 	= $flexformArray['show']['vDEF'];
        }

        if ($path == '') {
            $error[] = $this->getLanguageService()->getLL('error-path');
        }

        // any error occured?
        if (count($error) > 0) {
            foreach ($error as $single) {
                $errors .= '<li>' . $single . '</li>';
            }
            $content .= '<h2>' . $this->getLanguageService()->getLL('error-header') . '</h2>
                                        <div style="padding:10px;margin:10px;border:1px solid darkorange;font-style:bold;">
                                            <ul>' . $errors . '</ul>
                                        </div>	
                                        <a href="javascript:close();">' . $this->getLanguageService()->getLL('close') . '</a>
                ';
        } else {
            // get all the images from the directory
            $fileTypes = 'jpg,gif,png';

            #				$imageList = GeneralUtility::getFilesInDir(PATH_site.$path, $fileTypes,1,1);
            $imageList = GeneralUtility::getAllFilesAndFoldersInPath([], Environment::getPublicPath() . '/'.$path, $fileTypes, 0, 1);

            // correct sorting
            array_multisort($imageList, SORT_ASC);

            $content .= '<h2>'.sprintf($this->getLanguageService()->getLL('images'), count($imageList), $path).'</h2>' . $this->getLanguageService()->getLL('description');
            /*
            * save
            */
            $this->save($imageList);

            // create the textarea & preview for every image
            $i = 0;
            $directoryList = [];
            foreach ($imageList as $key => $singleImage) {
                $fileName = str_replace(Environment::getPublicPath() . '/', '', $singleImage);
                $directory = dirname(str_replace($path, '', $fileName));
                $thumbNailName = str_replace('fileadmin', '', $fileName);
                $thumb = $this->getThumbNail($thumbNailName, 100);
                if ($show != 'LIST') {
                    $fileName =  basename($singleImage);
                    $directory = $path;
                }
                $desc = $this->getSingleDescription($singleImage);
                if ($directory != '.') {
                    $directoryList[$directory] .= '<tr class="' . ($i++ % 2 == 0 ? 'bgColor3' : 'bgColor4').'">
                                                    <td align="center">' . $thumb . '</td>
                                                    <td>#' . $i . ': <strong>' . $fileName . '</strong><br /><br />
                                                            <textarea style="width:330px;" rows="2"  name="dir[' . $key . ']">' . $desc . '</textarea></td>
                                                </tr>';

                    // display a cutting line to show where a new page would begin
                    if ($pagebrowser > 0 && $i % $pagebrowser == 0) {
                        $directoryList[$directory] .= '<tr>
                                                    <td colspan="2" align="center"><strong>- - - - - - - - - - - - &#9985; - - - - - - - - - - - - - - - - - - - &#9985; - - - - - - - - - - - -</strong></td>
                                                </tr>';
                    }
                }

            }

            // ouput every directory including a header to toggle all images of the directory
            $i = 0;
            $hide = ($show == 'LIST') ? 'none' : 'block';
            foreach ($directoryList as $key => $value) {
                $content .= '<div onclick="toggle(\'item'.$i.'\')" style="font:weight:bold;cursor:pointer;background:#ccc;border:1px solid #333;margin-top:10px;padding:2px 5px;">
                                                <span style="margin:0 10px 0 5px;font-weight:bold;" id="icon' . $i . '">+</span>' . $key . '
                                            </div>
                                            <div id="item' . $i . '" style="border:1px solid #ccc;padding:0px;margin:5px;display:' . $hide . ';">
                                            <table cellpadding="1" cellspacing="1" class="bgColor4" width="100%" id="el">
                                            ' . $value . '
                                            </table></div>';
                $i++;
            }

            // wrap the form around
            [$rUri] = explode('#', GeneralUtility::getIndpEnv('REQUEST_URI'));
            // save the image titles, popup will be closes after submit
            $content = '
                    <form action="" action="'.htmlspecialchars($rUri) . '" method="post" name="editform">
                        ' . $content . '
                        <div id="send" style="margin:5px 10px;">
                            <input type="submit" value="' . $this->getLanguageService()->getLL('save2') . '" />
                            <br /><br /><a href="javascript:close();" >' . $this->getLanguageService()->getLL('close') . '</a>
                        </div>
                    </form>
                ';
        }

        // return everything
        $this->content .= $this->section('', $content, 0, 1);
    }

    /**
    * Save the descriptions to the txt file
    *
    * @param string	The file
    * @return string	The image-tag
    */
    public function save($imageList): void
    {
        $saveVars = GeneralUtility::_POST('dir');
        if(isset($saveVars) && is_array($saveVars) && count($saveVars) > 0) {
            foreach ($imageList as $key => $value) {
                GeneralUtility::writeFile($value. $this->languagePrefix . '.txt', $saveVars[$key]);
            }
        }
    }


    /**
    * Get the description of a file which is saved in a txt file with the same name.
    *
    * @param string	$file The file
    * @return string	The description
    */
    public function getSingleDescription($file)
    {
        $file = $file . $this->languagePrefix . '.txt';
        if (is_file($file)) {
            $text = file_get_contents($file);
        }
        return $text;
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

        if (!str_ends_with($path, '/')) { // check for needed / at the end
            $path =  $path.'/';
        }

        if (str_starts_with($path, '/')) { // check for / at the beginning
            $path = substr($path, 1, -1);
        }

        return $path;
    }

    /**
    * Returns a Thumbnail with maximum dimension of 100pixels
    *
    * @param string	The file
    * @return string	The image-tag
    */
    public function getThumbNail($fileName, $size = 100)
    {

        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $storage = $resourceFactory->getDefaultStorage();
        // $fileObject returns a TYPO3\CMS\Core\Resource\File object
        $fileReferenceObject = $storage->getFile($fileName);
        $processedImage = $fileReferenceObject->process(
            ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
            [
                'width' => $sizeParts[0],
                'height' => $sizeParts[1] . 'c',
                'crop' => $fileReferenceObject->getProperty('crop')
            ]
        );
        $imageUrl = $processedImage->getPublicUrl(true);
        $imgTag = '<img src="' . $imageUrl . '" ' .
                'width="' . $processedImage->getProperty('width') . '" ' .
                'height="' . $processedImage->getProperty('height') . '" ' .
                'alt="' . htmlspecialchars((string) $fileReferenceObject->getName()) . '" />';

        return $imgTag;
    }


    /**
    * Makes the header (icon+title) for a page (or other record). Used in most modules under Web>*
    * $table and $row must be a tablename/record from that table
    * $path will be shown as alt-text for the icon.
    * The title will be truncated to 45 chars.
    *
    * @param string $table Table name
    * @param array $row Record row
    * @param string $path Alt text
    * @param bool $noViewPageIcon Set $noViewPageIcon true if you don't want a magnifier-icon for viewing the page in the frontend
    * @param array $tWrap is an array with indexes 0 and 1 each representing HTML-tags (start/end) which will wrap the title
    * @param bool $enableClickMenu If true, render click menu code around icon image
    * @return string HTML content
    */
    public function getHeader($table, $row, $path, $noViewPageIcon = false, $tWrap = ['', ''], $enableClickMenu = true)
    {
        $viewPage = '';
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);

        if (is_array($row) && $row['uid']) {
            $iconImgTag = '<span title="' . htmlspecialchars($path) . '">' . $iconFactory->getIconForRecord($table, $row, Icon::SIZE_SMALL)->render() . '</span>';
            $title = strip_tags((string) BackendUtility::getRecordTitle($table, $row));
            $viewPage = $noViewPageIcon ? '' : $this->doc->viewPageIcon($row['uid']);
        } else {
            $iconImgTag = '<span title="' . htmlspecialchars($path) . '">' . $iconFactory->getIcon('apps-pagetree-page-domain', Icon::SIZE_SMALL)->render() . '</span>';
            $title = $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'];
        }

        if ($enableClickMenu) {
            $iconImgTag = BackendUtility::wrapClickMenuOnIcon($iconImgTag, $table, $row['uid']);
        }

        return '<span class="typo3-moduleHeader">' . $iconImgTag . $viewPage . $tWrap[0] . htmlspecialchars(GeneralUtility::fixed_lgd_cs($title, 45)) . $tWrap[1] . '</span>';
    }

    /**
     * Begins an output section and sets header and content
     *
     * @param string $label The header
     * @param string $text The HTML-content
     * @param bool $nostrtoupper	A flag that will prevent the header from being converted to uppercase
     * @param bool $sH Defines the type of header (if set, "<h3>" rather than the default "h4")
     * @param int $type The number of an icon to show with the header (see the icon-function). -1,1,2,3
     * @param bool $allowHTMLinHeader If set, HTML tags are allowed in $label (otherwise this value is by default htmlspecialchars()'ed)
     * @return string HTML content
     * @see icons(), sectionHeader()
     */
    public function section($label, $text, $nostrtoupper = false, $sH = false, $type = 0, $allowHTMLinHeader = false)
    {
        $str = '';
        // Setting header
        if ($label) {
            if (!$allowHTMLinHeader) {
                $label = htmlspecialchars($label);
            }
            $str .= $this->sectionHeader($this->icons($type) . $label, $sH, $nostrtoupper ? '' : ' class="uppercase"');
        }
        // Setting content
        $str .= '

	<!-- Section content -->
' . $text;
        return $this->sectionBegin() . $str;
    }

    /**
     * Make a section header.
     * Begins a section if not already open.
     *
     * @param string $label The label between the <h3> or <h4> tags. (Allows HTML)
     * @param bool $sH If set, <h3> is used, otherwise <h4>
     * @param string $addAttrib Additional attributes to h-tag, eg. ' class=""'
     * @return string HTML content
     */
    public function sectionHeader($label, $sH = false, $addAttrib = '')
    {
        $tag = $sH ? 'h2' : 'h3';
        if ($addAttrib && $addAttrib[0] !== ' ') {
            $addAttrib = ' ' . $addAttrib;
        }
        $str = '

	<!-- Section header -->
	<' . $tag . $addAttrib . '>' . $label . '</' . $tag . '>
';
        return $this->sectionBegin() . $str;
    }

    /**
     * Begins an output section.
     * Returns the <div>-begin tag AND sets the ->sectionFlag true (if the ->sectionFlag is not already set!)
     * You can call this function even if a section is already begun since the function will only return something if the sectionFlag is not already set!
     *
     * @return string HTML content
     */
    public function sectionBegin()
    {
        if (!$this->sectionFlag) {
            $this->sectionFlag = 1;
            $str = '

	<!-- ***********************
	      Begin output section.
	     *********************** -->
	<div>
';
            return $str;
        }
        return '';
    }

    /**
     * Ends and output section
     * Returns the </div>-end tag AND clears the ->sectionFlag (but does so only IF the sectionFlag is set - that is a section is 'open')
     * See sectionBegin() also.
     *
     * @return string HTML content
     * @deprecated since TYPO3 CMS 8, will be removed in TYPO3 CMS 9.
     */
    public function sectionEnd()
    {
        if ($this->sectionFlag) {
            trigger_error('A useful message', E_USER_DEPRECATED);
            $this->sectionFlag = 0;
            return '
	</div>
	<!-- *********************
	      End output section.
	     ********************* -->
';
        }
        return '';
    }

    /**
     * Returns a blank <div>-section with a height
     *
     * @param int $dist Padding-top for the div-section (should be margin-top but konqueror (3.1) doesn't like it :-(
     * @return string HTML content
     */
    public function spacer($dist)
    {
        if ($dist > 0) {
            return '

	<!-- Spacer element -->
	<div style="padding-top: ' . (int)$dist . 'px;"></div>
';
        }
    }

    /**
    * Main function of the module. Write the content to $this->content
    *
    * @return    [type]        ...
    */
    public function main(): void
    {
        ;
        $P = $var = GeneralUtility::_GP('P');

        // Draw the header.
        $this->doc = GeneralUtility::makeInstance(DocumentTemplate::class);

        // JavaScript
        $this->doc->JScode = '
            <script language="javascript" type="text/javascript">
                script_ended = 0;
                function jumpToUrl(URL)    {
                    document.location = URL;
                }
                                function toggle(obj) {
                                    var el = document.getElementById(obj); 
                                    if ( el.style.display != "none" ) {
                                        el.style.display = "none";
                                    }
                                    else {
                                        el.style.display = "";
                                    }
                                }                
            </script>
        ';
        $GLOBALS['TBE_TEMPLATE'] = $this->doc;

        $this->pageinfo = BackendUtility::readPageAccess($this->id, $this->perms_clause);
        $access = is_array($this->pageinfo) ? 1 : 0;
        if (($this->id && $access) || ($this->getBackendUser()->user['admin'] && !$this->id) || ($this->getBackendUser()->user["uid"] && !$this->id)) {
            if ($this->getBackendUser()->user['admin'] && !$this->id) {
                $this->pageinfo = ['title' => '[root-level]', 'uid'   => 0, 'pid'   => 0];
            }

            $headerSection = $this->getHeader('pages', $this->pageinfo, $this->pageinfo['_thePath']) . '<br />'
                    . $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xml:labels.path') . ': '  .GeneralUtility::fixed_lgd_cs($this->pageinfo['_thePath'], 50);

            $this->content .= $this->doc->startPage($this->getLanguageService()->getLL('title'));

            // Render content:
            $this->moduleContent();

        }
        $this->content .= $this->spacer(10);
    }

    /**
    * [Describe function...]
    *
    * @return    [type]        ...
    */
    public function printContent(): void
    {
        $this->content .= $this->doc->endPage();
        echo $this->content;
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder(string $tableName)
    {
        $result = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        return $result;
    }
}


// \TYPO3\CMS\Core\Core\Bootstrap::baseSetup();

// Set up the application for the backend
call_user_func(function (): void {

    $BACK_PATH = '../../../..';
    $entryPointLevel = 4;
    if (strpos((string) $_SERVER['PHP_SELF'], 'typo3conf')) {
        $BACK_PATH .= '/..';
        $entryPointLevel++;
        define('TYPO3_MOD_PATH', '../typo3conf/ext/chgallery/wizard/');
    } else {
        define('TYPO3_MOD_PATH', 'ext/chgallery/wizard/');
    }

    $classLoader = require $BACK_PATH . '/vendor/autoload.php';
    SystemEnvironmentBuilder::run(
        $entryPointLevel,
        SystemEnvironmentBuilder::REQUESTTYPE_BE
    );
    Bootstrap::init($classLoader);
    Bootstrap::initializeLanguageObject();
    Bootstrap::initializeBackendUser();
    Bootstrap::loadExtTables();


    $GLOBALS['MCONF']['name'] =   CHGALLERY_EXT . '_chgalleryM1';
    $GLOBALS['MCONF']['script'] = 'WizardController.php';

    // Make instance:
    $GLOBALS['SOBE'] = GeneralUtility::makeInstance(\JambageCom\Chgallery\Controller\WizardController::class);

    $GLOBALS['SOBE']->init();
    $GLOBALS['SOBE']->main();
    $GLOBALS['SOBE']->printContent();
});
