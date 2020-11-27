<?php

namespace JambageCom\Chgallery\Domain;

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
 * variable storage
 *
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;


class Composite implements \TYPO3\CMS\Core\SingletonInterface {
    protected $cObj;       // The backReference to the mother cObj object set at call time
    protected $prefixId;
    protected $conf = [];
    protected $config = [];
    protected $piVars = [];
    protected $languageObj = null;
    protected $templateCode = '';


    public function setCObj ($value)
    {
        $this->cObj = $value;
    }

    public function getCObj ()
    {
        return $this->cObj;
    }

    public function setPrefixId ($value)
    {
        $this->prefixId = $value;
    }

    public function getPrefixId ()
    {
        return $this->prefixId;
    }

    public function setPiVars ($value)
    {
        $this->piVars = $value;
    }

    public function getPiVars ()
    {
        return $this->piVars;
    }

    public function setConf ($value)
    {
        $this->conf = $value;
    }

    public function getConf ()
    {
        return $this->conf;
    }

    public function setConfig ($value)
    {
        $this->config = $value;
    }

    public function getConfig ()
    {
        return $this->config;
    }

    public function setTemplateCode ($value)
    {
        $this->templateCode = $value;
    }

    public function getTemplateCode ()
    {
        return $this->templateCode;
    }
}

