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
use craft\validators\ArrayValidator;

/**
 * CustomAttributes Model
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    dotsandlines GmbH
 * @package   SsoGluuModule
 * @since     1.0.0
 */
class CustomAttributes extends Model
{
    /**
     * @var ?string $oebvExtSystem
     */
    public ?string $oebvExtSystem = null;

    /**
     * @var ?string $oebvExtKey
     */
    public ?string $oebvExtKey = null;

    /**
     * @var ?string $oebvExtKey
     */
    public ?string $oebvKlebrId = null;

    /**
     * @var ?string[] $oebvPeType
     */
    public ?array $oebvPeType = null;

    /**
     * @var ?bool $oebvCheckPeType
     */
    public ?bool $oebvCheckPeType = null;

    /**
     * @var ?int[] $oebvSchoolCode
     */
    public ?array $oebvSchoolCode = null;


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
            [['oebvExtSystem', 'oebvExtKey', 'oebvKlebrId'], 'string'],
            ['oebvCheckPeType', 'boolean'],
            [['oebvPeType', 'oebvSchoolCode'], ArrayValidator::class],
        ];
    }
}