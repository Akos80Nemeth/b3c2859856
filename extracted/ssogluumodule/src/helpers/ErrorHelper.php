<?php
/**
 * SSO Gluu Module module for Craft CMS 3.x
 *
 * Provides Gluu integration
 *
 * @link      https://dotsandlines.io
 * @copyright Copyright (c) 2022 dotsandlines GmbH
 */

namespace modules\ssogluumodule\helpers;

/**
 * Error Helper
 *
 * @author    dotsandlines GmbH
 * @package   SsoGluuModule
 * @since     1.0.0
 */
class ErrorHelper
{
    /**
     * Returns string with all error messages
     * @param $errors
     * @access public
     * @return string
     */
    public static function getModelErrorMessages($errors): string
    {
        $errorMessage = '';
        $loopCount = 0;

        foreach ($errors as $error) {
            // if loop is not 0, add space
            if ($loopCount !== 0) {
                $errorMessage .= ' ';
            }

            // append the message
            $errorMessage .= $error[0];
            $loopCount++;
        }

        return $errorMessage;
    }
}
