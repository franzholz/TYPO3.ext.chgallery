<?php

namespace JambageCom\Chgallery\UserFunc;

/**
 * Class with backend TCA functions
 *
 * @category    Plugin
 * @package     TYPO3
 * @subpackage  chgallery
 * @author      Chgallery Team
 * @license     http://www.gnu.org/copyleft/gpl.html
 */



use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use JambageCom\Chgallery\Utility\FalUtility;

class GalleryTca
{
    /**
    * Set the DB TCA field with an userfunction to allow dynamic manipulation
    *
    * @param	array		$PA:
    * @param	array		$fobj:
    * @return	The		TCA field
    */
    public function descr($PA, $fobj)
    {
        // get the correct tables which are allowed > tsconfig chgallery.tables
        $pid = $this->getStorageFolderPid($PA['row']['pid']);
        $uid = $PA['row']['uid'];
        $pid = $PA['row']['pid'];

        if (is_numeric($uid)) {
            $link =  '==> 
            <a onclick="this.blur();vHWin=window.open(\'../typo3conf/ext/chgallery/Classes/Controller/WizardController.php?&P[params]=&P[exampleImg]=&P[table]=tt_content&P[uid]=' . $uid . '&P[pid]=' . $pid . '&P[field]=pi_flexform&P[md5ID]=ID24e035d4e1&P[returnUrl]=%2Ftypo3%2Falt_doc.php%3F%26returnUrl%3D%252Ftypo3%252Fsysext%252Fcms%252Flayout%252Fdb_layout.php%253Fid%253D' . $pid . '%26edit%5Btt_content%5D%5B' . $uid . '%5D%3Dedit&P[formName]=editform&P[itemName]=data%5Btt_content%5D%5B' . $uid . '%5D%5Bpi_flexform%5D%5Bdata%5D%5BsDEF%5D%5BlDEF%5D%5Bdescription%5D%5BvDEF%5D_hr&P[fieldChangeFunc][typo3form.fieldGet]=typo3form.fieldGet%28%27data%5Btt_content%5D%5B' . $uid . '%5D%5Bpi_flexform%5D%5Bdata%5D%5BsDEF%5D%5BlDEF%5D%5Bdescription%5D%5BvDEF%5D%27%2C%27%27%2C%27%27%2C0%2C%27%27%29%3B&P[fieldChangeFunc][TBE_EDITOR_fieldChanged]=TBE_EDITOR.fieldChanged%28%27tt_content%27%2C%27' . $uid . '%27%2C%27pi_flexform%27%2C%27data%5Btt_content%5D%5B' . $uid . '%5D%5Bpi_flexform%5D%27%29%3B&P[fieldChangeFunc][alert]=\'+\'&P[currentValue]=\'+TBE_EDITOR.rawurlencode(document.editform[\'data[tt_content][' . $uid . '][pi_flexform][data][sDEF][lDEF][description][vDEF]_hr\'].value,200)+\'&P[currentSelectedValues]=\'+TBE_EDITOR.curSelected(\'data[tt_content]['.$uid.'][pi_flexform][data][sDEF][lDEF][description][vDEF]_hr_list\'),\'popUpID24e035d4e1\',\'height=600,width=550,status=1,menubar=1,scrollbars=1\');vHWin.focus();return false;" href="#"><img width="16" height="16" border="0" title="Image Description Wizard" alt="Image Description Wizard" src="../typo3conf/ext/chgallery/wizard/wizard.gif"/></a>
            ';#?P[table]=tt_content&P[uid]='.$uid.'&P[pid]='.$pid.'&

            $onclick = "this.blur();vHWin=window.open('../typo3conf/ext/chgallery/Classes/Controller/WizardController.php?P[field]=pi_flexform&P[table]=tt_content&P[uid]=$uid&P[pid]=$pid','popUpID5a0ca3a2f5','height=600,width=550,status=1,menubar=1,scrollbars=1');vHWin.focus();return false;";
            $link = '<a onclick="' . $onclick . '" href="#"><strong>Click here</strong> => <img width="16" height="16" border="0" title="Image Description Wizard" alt="Image Description Wizard" src="../typo3conf/ext/chgallery/wizard/wizard.gif"/></a>';

            return $link;
        } else {
            return '<strong>Save at least once to be able to use the wizard</strong>';
        }
    }


    /**
    * Get a preview of the plugin in the last tab of the plugin.
    * Just output, nothing is saved to anywhere
    *
    * @param	array		$PA:
    * @param	array		$fobj:
    * @return	The		TCA field
    */
    public function preview($PA, $fobj)
    {
        $content = '';
        $uid = $PA['row']['uid'];
        $pid = $PA['row']['pid'];

        if (
            is_numeric($uid) &&
            is_array($PA['row']['pi_flexform'])
        ) {
            $fileTypes = 'gif,jpg,png';

            // read the flexform settings and transform it to array
            $flexformArray = $PA['row']['pi_flexform']['data']['sDEF']['lDEF'];

            // get all the infos we need
            $path = trim($flexformArray['path']['vDEF']);
            $mode = trim($flexformArray['show']['vDEF']);
            $sort  = trim($flexformArray['categoryOrder']['vDEF']);
            $languageid = $PA['row']['sys_language_uid'];

            $path = FalUtility::convertFalPath($path);

            if ($path != '' && is_dir(PATH_site . $path)) {
                $content .= '<h3>Path: ' . $path . '  <small>Mode: ' . $mode . '</small></h3>';
                $this->languagePrefix = ($languageid > 0) ? '-' . $languageid : '';

                $level = ($mode == 'LIST') ? 1 : 0;
                $imageList = GeneralUtility::getAllFilesAndFoldersInPath([], PATH_site.$path, $fileTypes, 0, $level, 1);

                // correct sorting
                array_multisort($imageList, SORT_ASC);


                $directoryList = [];
                // get each dir in an array
                foreach ($imageList as $key => $singleImage) {
                    // get the correct path
                    if ($mode == 'LIST') {
                        $fileName = str_replace(PATH_site, '', $singleImage);
                        $directory = dirname(str_replace($path, '', $fileName));
                    } else {
                        $fileName =  basename($singleImage);
                        $directory = $path;
                    }

                    // Files of a directory title
                    if ($directory != '.') {
                        $desc = $this->getSingleDescription($singleImage);
                        $desc = $desc ? ' - <i>' . $desc . '</i>' : '';
                        $directoryList[$directory] .= basename($fileName) . $desc . '<br />';
                    }
                }

                // ouput every directory including a header to toggle all images of the directory
                foreach ($directoryList as $key => $value) {
                    if ($mode == 'LIST') {
                        $content .= '<div style="font:weight:bold;background:#eee;border:1px solid #333;margin-top:10px;padding:2px 5px;">
                                                    ' . $key . '
                                     </div>';
                    }
                    $content .= '<div style="padding:0 5px;margin:5px;">
                                                ' . $value . '
                                 </div>';

                }
            }


        } else {
            $content = '<strong>Save at least once to be able to use the preview</strong>';
        }

        return $content;
    }

    /**
    * Get the description of a file which is saved in a txt file with the same name.
    *
    * @param string	$file The file
    * @return string	The description
    */
    protected function getSingleDescription($file)
    {
        $file = $file . $this->languagePrefix . '.txt';
        if (is_file($file)) {
            $text = file_get_contents($file);
        }
        return $text;
    }

    /**
    * Negative PID values is pointing to a page on the same level as the current.
    *
    * @param	int		$pid: The pid of the record
    * @return	The		real pid of a (new) record
    */
    protected function getStorageFolderPid($pid)
    {
        if ($pid < 0) {
            $pidRow = BackendUtility::getRecord('tt_content', abs($pid), 'pid');
            $pid = $pidRow['pid'];
        }
        return $pid;
    }
}
