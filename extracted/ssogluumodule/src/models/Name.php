<?php
/**
 * SSO Gluu Module module for Craft CMS 3.x
 *
 * Provides Gluu integration
 *
 * @link      https://dotsandlines.io
 * @copyright Copyright (c) 2022 dotsandlines GmbH
 */

namespace modules\ssogluumodule\models;

use craft\base\Model;

class Name extends Model
{
    /**
     * @var string $familyName
     */
    public string $familyName;

    /**
     * @var string $givenName
     */
    public string $givenName;

    /**
     * @var ?string $formatted
     */
    public ?string $formatted;

    // Public Methods
    // =========================================================================
    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            [['familyName', 'givenName', 'formatted'], 'string'],
            [['familyName', 'givenName'], 'required']
        ];
    }
}