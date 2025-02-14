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

use SimpleXMLElement;

/**
 * Crowd Helper
 *
 * @author    dotsandlines GmbH
 * @package   SsoGluuModule
 * @since     1.0.0
 */
class CrowdHelper
{
    public static function arrayOrObjToXml($data, SimpleXMLElement &$xmlObj): SimpleXMLElement
    {
        foreach ((array)$data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'n' . $key;
            }
            if (is_array($value) or is_object($value)) {
                $subNode = $xmlObj->addChild($key);
                self::arrayOrObjToXml($value, $subNode);
            } else {
                $xmlObj->addChild($key, htmlspecialchars($value));
            }
        }

        return $xmlObj;
    }

    /**
     * @param string $userKlebrID
     * @param string $password
     * @return array
     */

    public static function createPayload(string $userKlebrID, string $password): array
    {
        return [
            'username' => $userKlebrID,
            'password' => $password,
            'validation-factors' => [
                'validationFactors' => [
                    [
                        'name' => 'remote_address',
                        'value' => '127.0.0.1'
                    ]
                ]
            ]
        ];
    }
}
