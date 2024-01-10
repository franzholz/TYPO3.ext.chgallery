<?php

namespace JambageCom\Chgallery\UserFunc;

class ExtraEval
{
    /**
     * JavaScript code for client side validation/evaluation
     *
     * @return string JavaScript code for client side validation/evaluation
     */
    public function returnFieldJS()
    {
        return 'if (value.length==0) return value;
                        else {
                            if (value.charAt(0)=="/") value = value.slice(1,value.length);
                            if (value.charAt(value.length-1)!="/") value = value + "/";
                        }
                        
                        return value;';
    }

    /**
     * Server-side validation/evaluation on saving the record
     *
     * @param string $value The field value to be evaluated
     * @param string $is_in The "is_in" value of the field configuration from TCA
     * @param bool $set Boolean defining if the value is written to the database or not.
     * @return string Evaluated field value
     */
    public function evaluateFieldValue($value, $is_in, &$set)
    {
        $value = trim($value);

        if ($value == '') {
            return $value;
        }

        if (!str_ends_with($value, '/')) { // check for needed / at the end
            $value =  $value . '/';
        }

        if (str_starts_with($value, '/')) { // check for / at the beginning
            $patßh = substr($value, 1, strlen($value));
        }

        return $value;
    }
}
