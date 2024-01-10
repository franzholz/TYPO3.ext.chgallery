<?php

/***************************************************************
* Extension Manager/Repository config file for ext "chgallery".
***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'Another simple gallery, show all images of a directory',
    'description' => 'Simple gallery with images from a directory including categories, pagebrowser, lightbox, ratings. Very easy to use featuring templates and TS manipulation. Visit the demo: http://www.rggooglemap.com/dev/chgallery.html',
    'category' => 'fe',
    'version' => '3.1.0',
    'state' => 'stable',
    'author' => 'Chgallery Team',
    'author_email' => '',
    'author_company' => '',
    'constraints' => [
        'depends' => [
			'php' => '8.2.0-8.4.99',
            'typo3' => '12.4.0-12.4.99',
            'div2007' => '2.0.0-0.0.0',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];

