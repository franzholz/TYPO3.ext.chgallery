<?php

namespace JambageCom\Chgallery\UserFunc;


class String {
    /**
    * user function to replace spaces with rawurlencoded %20
    * to avoid breaking lightboxes 
    * Call it with stdWrap.postUserFunc = user_trimSpaces	 	 
    *
    * @param	string		$file: The file including the path
    * @param	array		$conf: possible configuration
    * @return	corrected path+file
    */
    public function replaceSpaces($file, $conf) {
        $search = array(' ');
        $replace = array('%20');
        $file = str_replace($search, $replace, $file);
        return $file;
    }
}

