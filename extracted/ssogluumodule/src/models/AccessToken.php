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
use RuntimeException;

/**
 * AccessToken
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    dotsandlines GmbH
 * @package   SsoGluuModule
 * @since     1.0.0
 *
 * @property string $accessToken
 * @property string $tokenType
 * @property int|null $expiresIn
 * @property string|null $refreshToken
 * @property string|null $scope
 * @property string|null $idToken
 */
class AccessToken extends Model
{
    /** @var string */
    private string $accessToken = '';

    /** @var string */
    private string $tokenType = '';

    /** @var int */
    private int $expiresIn = 0;

    /** @var ?string */
    private ?string $refreshToken = null;

    /** @var ?string */
    private ?string $scope = null;

    /** @var ?string */
    private ?string $idToken = null;

    /** @var int */
    private int $buffer = 10;

    /**
     * @return string
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * @return string
     */
    public function getTokenType(): string
    {
        return $this->tokenType;
    }

    /**
     * @return int|null
     */
    public function getExpiresIn(): ?int
    {
        return $this->expiresIn;
    }

    /**
     * @return string|null
     */
    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    /**
     * @return string|null
     */
    public function getScope(): ?string
    {
        return $this->scope;
    }

    /**
     * @return string|null
     */
    public function getIdToken(): ?string
    {
        return $this->idToken;
    }

    /**
     * @param
     * @return bool
     */
    public function isExpired(): bool
    {
        if ($this->getExpiresIn() === null) {
            return false;
        }

        return time() + $this->buffer >= $this->getExpiresIn();
    }

    /**
     * @param string $accessToken
     * @return void
     */
    public function setAccessToken(string $accessToken): void
    {
        if (preg_match('/^[\x20-\x7E]+$/', $accessToken) !== 1) {
            throw new RuntimeException('invalid "access_token"');
        }
        $this->accessToken = $accessToken;
    }

    /**
     * @param string $tokenType
     * @return void
     */
    public function setTokenType(string $tokenType): void
    {
        if ('bearer' !== $tokenType) {
            throw new RuntimeException('unsupported "token_type"');
        }
        $this->tokenType = $tokenType;
    }

    /**
     * @param ?string $refreshToken
     * @return void
     */
    public function setRefreshToken(?string $refreshToken): void
    {
        if ($refreshToken && preg_match('/^[\x20-\x7E]+$/', $refreshToken) !== 1) {
            throw new RuntimeException('invalid "refresh_token"');
        }
        $this->refreshToken = $refreshToken;
    }

    /**
     * @param ?string $scope
     * @return void
     */
    public function setScope(?string $scope): void
    {
        $this->scope = $scope;
    }

    /**
     * @param ?string $idToken
     * @return void
     */
    public function setIdToken(?string $idToken): void
    {
        $this->idToken = $idToken;
    }

    /**
     * @param int|null $expiresIn
     * @return void
     */
    public function setExpiresIn(?int $expiresIn): void
    {
        if ($expiresIn !== null) {
            if ($expiresIn <= 0) {
                throw new RuntimeException('invalid "expires_in"');
            }

            if (preg_match('/^\d{4}$/', $expiresIn)) {
                $expiresIn = time() + $expiresIn;
            }
        }

        $this->expiresIn = $expiresIn;
    }

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
            [['accessToken', 'tokenType', 'refreshToken', 'scope', 'idToken'], 'string'],
            ['expiresIn', 'integer'],
            [['accessToken', 'tokenType', 'expiresIn'], 'required']
        ];
    }
}
