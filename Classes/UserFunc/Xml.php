<?php

namespace JambageCom\Chgallery\UserFunc;

class Xml extends \TYPO3\CMS\Frontend\Plugin\AbstractPlugin
{
    public $prefixId      = 'tx_chgallery_pi1';		// Same as class name
    public $scriptRelPath = 'pi1/class.tx_chgallery_pi1.php';	// Path to this script relative to the extension dir.
    public $extKey        = CHGALLERY_EXT;	// The extension key.
    public $pi_checkCHash = true;


    /**
     * The xml method of the PlugIn. Used for the ajax connection to get the gallery pages
     *
     * @param	string		$content: The PlugIn content
     * @param	array		$conf: The PlugIn configuration
     * @return	The single gallery page
     */
    public function main($content, $conf)
    {
        $this->init($content, $conf);

        if ($this->conf['ajax'] == 1) {
            $pb = intval(GeneralUtility::_GP('pb'));

            // page browser
            $begin 	= $pb * $this->config['pagebrowser'];
            $end 		= $begin + $this->config['pagebrowser'];
            $this->piVars['pointer'] = $pb;

            $content = $this->getSingleGalleryPage($pb, $begin, $end, $ajax = 1);

            $xml .= '<tab>' . $content . '</tab>';
        } else {
            $xml .= '<p><b>Ajax is not activated!</b></p>';
        }

        return $xml;
    }

    public function init(&$content, $conf)
    {
        $initialization = GeneralUtility::makeInstance(
            \JambageCom\Chgallery\Controller\InitializationController::class
        );
        $composite = null;
        $initialization->init(
            $composite,
            $this->piVars,
            $this->cObj,
            $conf,
            $content,
            $this->prefixId
        );

        return $composite;
    }
}
