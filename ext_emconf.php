<?php

/***************************************************************
* Extension Manager/Repository config file for ext "chgallery".
***************************************************************/

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Another simple gallery, show all images of a directory',
    'description' => 'Simple gallery with images from a directory including categories, pagebrowser, lightbox, ratings. Very easy to use featuring templates and TS manipulation. Visit the demo: http://www.rggooglemap.com/dev/chgallery.html',
    'category' => 'fe',
    'version' => '3.0.0',
    'state' => 'stable',
    'author' => 'Chgallery Team',
    'author_email' => '',
    'author_company' => '',
    'constraints' => array(
        'depends' => array(
			'php' => '5.5.0-7.3.99',
            'typo3' => '9.5.0-9.5.99',
            'div2007' => '1.10.33-0.0.0',
        ),
        'conflicts' => array(
        ),
        'suggests' => array(
        ),
    ),
);

