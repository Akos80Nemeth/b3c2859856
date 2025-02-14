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

use craft\db\ActiveRecord;
use craft\validators\DateTimeValidator;

/**
 * @property int $expiresIn
 * @property int $userId
 * @property string $accessToken
 * @property string $tokenType
 * @property string $refreshToken
 * @property string $scope
 * @property string $idToken
 */
class GluuUserSession extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%gluu_user_sessions}}';
    }

    public function rules(): array
    {
        return [
            [['expiresIn', 'userId'], 'integer'],
            [['accessToken', 'tokenType', 'refreshToken', 'scope', 'idToken'], 'string'],
            [['dateCreated', 'dateUpdated'], DateTimeValidator::class],
            [['expiresIn', 'userId', 'accessToken', 'tokenType'], 'required']
        ];
    }
}
