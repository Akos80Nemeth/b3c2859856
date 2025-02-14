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
use modules\ssogluumodule\SsoGluuModule;

/**
 * User Model
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
class GluuUser extends Model
{
    // Public Properties
    // =========================================================================
    /**
     * @var array $schemas
     */
    public array $schemas = [SsoGluuModule::USER_SCHEMA];

    /**
     * @var string $userName
     */
    public string $userName = '';

    /**
     * @var ?Email[] $emails
     */
    public ?array $emails = null;

    /**
     * @var ?string $id
     */
    public ?string $id = null;

    /**
     * @var ?Meta $meta
     */
    public ?Meta $meta = null;

    /**
     * @var ?Name $name
     */
    public ?Name $name = null;

    /**
     * @var ?string $displayName
     */
    public ?string $displayName = null;

    /**
     * @var ?string $nickName
     */
    public ?string $nickName = null;

    /**
     * @var ?string $preferredLanguage
     */
    public ?string $preferredLanguage = null;

    /**
     * @var ?bool $active
     */
    public ?bool $active = null;

    /**
     * @var ?CustomAttributes $customAttributes
     */
    public ?CustomAttributes $customAttributes = null;


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
            [['schemas', 'emails'], ArrayValidator::class],
            [['id', 'userName', 'displayName', 'nickName'], 'string'],
            [['schemas', 'userName'], 'required'],
        ];
    }
}
