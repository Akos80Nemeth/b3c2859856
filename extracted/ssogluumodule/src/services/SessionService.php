<?php
/**
 * SSO Gluu Module module for Craft CMS 3.x
 *
 * Provides Gluu integration
 *
 * @link      https://dotsandlines.io
 * @copyright Copyright (c) 2022 dotsandlines GmbH
 */

namespace modules\ssogluumodule\services;

use Craft;
use craft\base\Component;
use craft\helpers\Json;
use craft\helpers\Session as SessionHelper;
use modules\ssogluumodule\models\AccessToken;
use modules\ssogluumodule\models\GluuUserSession;
use modules\ssogluumodule\SsoGluuModule;
use yii\base\InvalidConfigException;
use yii\web\Cookie;

/**
 * Session Service
 *
 * All of your module’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other modules can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    dotsandlines GmbH
 * @package   SsoGluuModule
 * @since     1.0.0
 */
class SessionService extends Component
{
    /**
     * @param string $clientName
     *
     * @return AccessToken|null
     */
    public function getAccessToken(string $clientName): ?AccessToken
    {
        $accessToken = SessionHelper::get(sprintf('_oebv_token_%s', $clientName));

        // if we can't find the accessToken in our session and if we need a user access token, let's have a look in our gluu table
        if (!$accessToken && $clientName !== SsoGluuModule::ADMIN_ACCESS_TOKEN_NAME) {
            $gluuUserSession = GluuUserSession::findOne(['userId' => $clientName]);
            if ($gluuUserSession) {
                $accessToken = new AccessToken();
                $accessToken->accessToken = $gluuUserSession->accessToken ?? null;
                $accessToken->tokenType = $gluuUserSession->tokenType ?? null;
                $accessToken->expiresIn = $gluuUserSession->expiresIn ?? null;
                $accessToken->refreshToken = $gluuUserSession->refreshToken ?? null;
                $accessToken->scope = $gluuUserSession->scope ?? null;
                $accessToken->idToken = $gluuUserSession->idToken ?? null;

                // let's store our access token again in our session
                $this->storeAccessToken($clientName, $accessToken, true);
            }
        }

        return $accessToken;
    }

    /**
     * @param string $clientName
     * @param AccessToken $accessToken
     * @param bool $saveIntoSessionOnly
     * @return void
     */
    public function storeAccessToken(string $clientName, AccessToken $accessToken, bool $saveIntoSessionOnly = false): void
    {
        SessionHelper::set(sprintf('_oebv_token_%s', $clientName), $accessToken);
        if ($clientName !== SsoGluuModule::ADMIN_ACCESS_TOKEN_NAME && !$saveIntoSessionOnly) {
            $this->deleteFromDatabase($clientName);
            $this->storeToDatabase($clientName, $accessToken);
        }
    }

    /**
     * @param string $clientName
     *
     * @return void
     */
    public function deleteAccessToken(string $clientName): void
    {
        SessionHelper::remove(sprintf('_oebv_token_%s', $clientName));
        if ($clientName !== SsoGluuModule::ADMIN_ACCESS_TOKEN_NAME) {
            $this->deleteFromDatabase($clientName);
        }
    }

    /**
     * @param string $token
     * @return void
     * @throws InvalidConfigException
     */
    public function saveAccessCookie(string $token): void
    {
        /** @var Cookie $cookie */
        $cookie = Craft::createObject(
            Craft::cookieConfig([
                'class' => Cookie::class,
                'name' => Craft::$app->getConfig()->getCustom()->crowdSsoCookieKey,
                'value' => $token,
                'expire' => 0,
                'domain' => SsoGluuModule::CROWD_SSO_COOKIE_DOMAIN,
                'sameSite' => 'None'
            ])
        );

        // Set cookie.
        Craft::$app->getResponse()->getRawCookies()->add($cookie);
    }

    /**
     * @param string $clientName
     * @param AccessToken $accessToken
     * @return void
     */
    private function storeToDatabase(string $clientName, AccessToken $accessToken): void
    {
        $gluuUserSession = new GluuUserSession();
        $gluuUserSession->userId = $clientName;
        $gluuUserSession->accessToken = $accessToken->getAccessToken();
        $gluuUserSession->tokenType = $accessToken->getTokenType();
        $gluuUserSession->refreshToken = $accessToken->getRefreshToken();
        $gluuUserSession->scope = $accessToken->getScope();
        $gluuUserSession->idToken = $accessToken->getIdToken();
        $gluuUserSession->expiresIn = $accessToken->getExpiresIn();

        if (!$gluuUserSession->save()) {
            Craft::error('Cant save gluu user session: ' . Json::encode($gluuUserSession->getErrors()));
            Craft::error(Json::encode($gluuUserSession));
        }
    }

    private function deleteFromDatabase(string $clientName): void
    {
        GluuUserSession::deleteAll(['userId' => $clientName]);
    }
}
