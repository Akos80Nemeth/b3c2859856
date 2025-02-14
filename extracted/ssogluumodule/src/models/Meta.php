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

class Meta extends Model
{
    /**
     * @var string $resourceType
     */
    public string $resourceType;

    /**
     * @var string $created
     */
    public string $created;

    /**
     * @var string $lastModified
     */
    public string $lastModified;

    /**
     * @var string $location
     */
    public string $location;

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
            [['resourceType', 'created', 'lastModified', 'location'], 'string']
        ];
    }
}