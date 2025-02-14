<?php
/**
 * SSO Gluu Module module for Craft CMS 3.x
 *
 * Provides Gluu integration
 *
 * @link      https://dotsandlines.io
 * @copyright Copyright (c) 2022 dotsandlines GmbH
 */

namespace modules\ssogluumodule\clients;

use Craft;
use craft\base\Component;
use craft\errors\MissingComponentException;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\Session as SessionHelper;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use modules\ssogluumodule\errors\AccessTokenException;
use modules\ssogluumodule\helpers\ErrorHelper;
use modules\ssogluumodule\helpers\GluuHelper;
use modules\ssogluumodule\models\AccessToken;
use modules\ssogluumodule\models\Collection;
use modules\ssogluumodule\models\GluuUser;
use modules\ssogluumodule\services\Request;
use modules\ssogluumodule\SsoGluuModule;
use modules\userutils\models\UserLoginModel;
use modules\userutils\models\UserRegisterModel;
use RuntimeException;
use Throwable;
use yii\web\ServerErrorHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * GluuClient
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
class GluuClient extends Component
{
    /**
     * @var string client id value
     */
    private string $clientId;

    /**
     * @var string client name value
     */
    private string $clientName;

    /**
     * @var string client secret value
     */
    private string $clientSecret;

    /**
     * @var bool
     */
    private bool $verifyPeer = false;

    /**
     * @var Request
     */
    private Request $httpClient;

    /**
     * @throws MissingComponentException|Exception
     * @throws Throwable
     */
    public function __construct(string $clientName)
    {
        parent::__construct();

        if (!SessionHelper::exists()) {
            Craft::$app->getSession()->open();
        }

        $this->clientId = App::env('GLUU_CLIENT_ID');
        $this->clientSecret = App::env('GLUU_CLIENT_SECRET');
        $this->clientName = $clientName;
        $this->httpClient = new Request();
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    /**
     * @param $clientName
     * @return void
     */
    public function setClientName($clientName): void
    {
        $this->clientName = $clientName;
    }

    /**
     * Search user in gluu by userName
     * using super client token
     *
     * @param string $queryParam
     * @return GluuUser
     * @throws GuzzleException
     * @throws MissingComponentException
     * @throws ServerErrorHttpException
     *
     * ```php
     * return $this->getUserByUserName('online@oebv.at');
     * ```
     */
    public function getUserByUserName(string $queryParam): GluuUser
    {
        $query = '?filter=userName eq "' . $queryParam . '"';
        $accessToken = $this->requestToken();

        try {
            $response = $this->httpClient->get(
                SsoGluuModule::USER_ENDPOINT . $query,
                [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $accessToken->getAccessToken()),
                    ],
                    'http_errors' => false,
                    'verify' => $this->verifyPeer,
                    'allow_redirects' => false,
                ]
            );

            if ($response->getStatusCode() === 200) {
                $responseBody = Json::decode((string)$response->getBody());
                if (isset($responseBody['totalResults']) && $responseBody['totalResults'] === 0) {
                    Craft::error('Gluu user search response has no result. Filter: ' . $queryParam);
                    throw new ServerErrorHttpException('Unexpected server error');
                }

                $user = new GluuUser();
                $user->attributes = $responseBody['Resources'][0];
                if ($user->validate()) {
                    return $user;
                }

                Craft::error(ErrorHelper::getModelErrorMessages($user->getErrors()), __METHOD__);
                throw new ServerErrorHttpException('Unexpected server error');
            }

            Craft::error(
                sprintf(
                    'Getting code %s from server while searching for user. Message: %s',
                    $response->getStatusCode(),
                    $response->getReasonPhrase()
                ),
                __METHOD__
            );
        } catch (ClientException|ServerException|Exception $e) {
            Craft::error(
                sprintf(
                    'Getting code %s from server while searching for user. Message: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                __METHOD__
            );
            throw new ServerErrorHttpException('Unexpected error');
        }

        throw new ServerErrorHttpException('Unexpected error');
    }

    /**
     * get user extension data in gluu by user id
     * using super client token
     *
     * @param string $id
     * @return GluuUser
     * @throws GuzzleException
     * @throws MissingComponentException
     * @throws ServerErrorHttpException
     */
    public function getUserById(string $id): GluuUser
    {
        $accessToken = $this->requestToken();
        try {
            $response = $this->httpClient->get(
                SsoGluuModule::USER_ENDPOINT . '/' . $id,
                [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $accessToken->getAccessToken()),
                    ],
                    'http_errors' => false,
                    'verify' => $this->verifyPeer,
                    'allow_redirects' => false,
                ]
            );

            if ($response->getStatusCode() === 200) {
                $user = GluuHelper::createGluuUser($response);

                if ($user->validate()) {
                    return $user;
                }

                // silent failure
                Craft::error(ErrorHelper::getModelErrorMessages($user->getErrors()), __METHOD__);
                throw new ServerErrorHttpException('Unexpected server error');
            }

            if ($response->getStatusCode() === 404) {
                // silent failure
                Craft::error($response->getReasonPhrase(), __METHOD__);
                throw new ServerErrorHttpException('Unexpected server error');
            }

            Craft::error(ErrorHelper::getModelErrorMessages($response->getReasonPhrase()), __METHOD__);
            throw new ServerErrorHttpException('Unexpected server error');
        } catch (ClientException|ServerException|Exception $e) {
            Craft::error(
                sprintf(
                    'Getting code %s from server while trying to get user by id. Message: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                __METHOD__
            );
            throw new ServerErrorHttpException('Unexpected error');
        }
    }

    /**
     * Get user by collection
     *
     * @param Collection $collection
     * @return GluuUser|null
     */
    public function getUserByCollection(Collection $collection): ?GluuUser
    {
        if ($collection->totalResults === 1 && isset($collection->resources[0])) {
            return $collection->resources[0];
        }

        return null;
    }

    /**
     * Validates old user password in gluu before we set a new password
     *
     * @param string $email
     * @param string $password
     * @return bool
     * @throws GuzzleException
     * @throws MissingComponentException
     * @throws ServerErrorHttpException
     * @see \modules\userutils\controllers\AuthController::actionSetPassword
     */
    public function validatePassword(string $email, string $password): bool
    {
        $accessToken = $this->requestToken();

        $response = $this->httpClient->post(
            SsoGluuModule::REQUEST_ACCESS_TOKEN_URL,
            [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $accessToken->getAccessToken()),
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'form_params' => [
                    'grant_type' => 'password',
                    'scope' => 'openid',
                    'username' => $email,
                    'password' => $password
                ],
                'http_errors' => false,
                'verify' => $this->verifyPeer,
                'allow_redirects' => false,
            ]
        );

        if ($response->getStatusCode() === 200) {
            return true;
        }

        return false;
    }

    /**
     * Creates user client token
     *
     * @param UserLoginModel $userLoginData
     * @param int $userId
     * @return AccessToken
     * @throws GuzzleException
     * @throws ServerErrorHttpException
     * @throws UnauthorizedHttpException
     */
    public function login(UserLoginModel $userLoginData, int $userId): AccessToken
    {
        $response = $this->httpClient->post(
            SsoGluuModule::REQUEST_ACCESS_TOKEN_URL,
            [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'auth' => [
                    $this->clientId,
                    $this->clientSecret
                ],
                'form_params' => [
                    'grant_type' => 'password',
                    'scope' => 'offline_access',
                    'username' => $userLoginData->email,
                    'password' => $userLoginData->password
                ],
                'http_errors' => false,
                'verify' => $this->verifyPeer,
                'allow_redirects' => false,
            ]
        );

        if ($response->getStatusCode() === 200) {
            $responseBody = Json::decode((string)$response->getBody());
            $accessToken = GluuHelper::setAccessTokenByResponseBody($responseBody);

            if ($accessToken->validate()) {
                SsoGluuModule::$instance->sessionService->storeAccessToken((string)$userId, $accessToken);
                return $accessToken;
            }

            Craft::error(ErrorHelper::getModelErrorMessages($accessToken->getErrors()), __METHOD__);
            throw new ServerErrorHttpException('Unexpected server error');
        }

        if ($response->getStatusCode() === 400 || $response->getStatusCode() === 401) {
            throw new UnauthorizedHttpException('Unauthorized');
        }

        throw new ServerErrorHttpException('Unexpected error');
    }

    /**
     * Updates user in gluu
     *
     * @param string $userGluuId
     * @param array $payload
     * @return GluuUser
     * @throws GuzzleException
     * @throws MissingComponentException
     * @throws ServerErrorHttpException
     */
    public function updateUser(string $userGluuId, array $payload): GluuUser
    {
        $accessToken = $this->requestToken();

        Craft::info('update user on gluu');

        try {
            $response = $this->httpClient->put(
                SsoGluuModule::USER_ENDPOINT . '/' . $userGluuId,
                [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $accessToken->getAccessToken()),
                        'Content-Type' => 'application/scim+json'
                    ],
                    'json' => $payload,
                    'verify' => $this->verifyPeer,
                    'allow_redirects' => false
                ]
            );

            if ($response->getStatusCode() === 200) {
                $user = GluuHelper::createGluuUser($response);

                if ($user->validate()) {
                    return $user;
                }
            }

            Craft::error(
                sprintf(
                    'Getting code %s from server while updating user. Message: %s',
                    $response->getStatusCode(),
                    $response->getReasonPhrase()
                ),
                __METHOD__
            );
            throw new ServerErrorHttpException('Unexpected server error');
        } catch (ClientException|ServerException|Exception $e) {
            Craft::error(
                sprintf(
                    'Getting code %s from server while updating user. Message: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                __METHOD__
            );
            throw new ServerErrorHttpException('Unexpected server error');
        }
    }

    /**
     * Soft-delete user in gluu
     *
     * @param string $userId
     * @return GluuUser
     * @throws GuzzleException
     * @throws MissingComponentException
     * @throws ServerErrorHttpException
     */
    public function deleteUser(string $userId): GluuUser
    {
        $accessToken = $this->requestToken();

        try {
            $response = $this->httpClient->put(
                SsoGluuModule::USER_ENDPOINT . '/' . $userId,
                [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $accessToken->getAccessToken()),
                        'Content-Type' => 'application/scim+json'
                    ],
                    'json' => ['active' => false],
                    'http_errors' => false,
                    'verify' => $this->verifyPeer,
                    'allow_redirects' => false
                ]
            );

            if ($response->getStatusCode() === 200) {
                $user = GluuHelper::createGluuUser($response);

                if ($user->validate()) {
                    return $user;
                }
            }

            Craft::error(
                sprintf(
                    'Getting code %s from server while deleting user. Message: %s',
                    $response->getStatusCode(),
                    $response->getReasonPhrase()
                ),
                __METHOD__
            );
            throw new ServerErrorHttpException('Unexpected server error');
        } catch (ClientException|ServerException|Exception $e) {
            Craft::error(
                sprintf(
                    'Getting code %s from server while deleting user. Message: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                __METHOD__
            );
            throw new ServerErrorHttpException('Unexpected server error');
        }
    }

    /**
     * Creates new user in gluu
     *
     * @param UserRegisterModel $userData
     * @return GluuUser
     * @throws GuzzleException
     * @throws MissingComponentException
     * @throws ServerErrorHttpException
     */
    public function createUser(UserRegisterModel $userData): GluuUser
    {
        $accessToken = $this->requestToken();

        $payload = GluuHelper::prepareCreateUserPayload($userData);
        try {
            $response = $this->httpClient->post(
                SsoGluuModule::USER_ENDPOINT,
                [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $accessToken->getAccessToken()),
                        'Content-Type' => 'application/scim+json'
                    ],
                    'json' => $payload,
                    'http_errors' => false,
                    'verify' => $this->verifyPeer,
                    'allow_redirects' => false,
                ]
            );

            if ($response->getStatusCode() === 201) {
                $user = GluuHelper::createGluuUser($response);

                if ($user->validate()) {
                    return $user;
                }

                Craft::error(ErrorHelper::getModelErrorMessages($user->getErrors()), __METHOD__);
                throw new ServerErrorHttpException('Unexpected server error');
            }

            Craft::error(
                sprintf('Getting code %s from server while creating a new user.', $response->getStatusCode()),
                __METHOD__
            );
            throw new ServerErrorHttpException('Unexpected server error');
        } catch (Throwable $e) {
            Craft::error(
                sprintf(
                    'Getting code %s from server while creating a new user. Message: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                __METHOD__
            );
            throw new ServerErrorHttpException('Unexpected server error');
        }
    }

    /**
     * Creates access token or returns existing tokens
     *
     * @return AccessToken
     * @throws GuzzleException
     * @throws MissingComponentException
     * @throws ServerErrorHttpException
     * @throws RuntimeException
     */
    private function requestToken(): AccessToken
    {
        $accessToken = SsoGluuModule::$instance->sessionService->getAccessToken($this->clientName);
        if ($accessToken && !$accessToken->isExpired()) {
            return $accessToken;
        }

        // if it's user access token, let's try to get a new token by the refresh token
        if ($accessToken && $accessToken->isExpired() && $this->clientName === SsoGluuModule::USER_ACCESS_TOKEN_NAME) {
            $refreshToken = $accessToken->getRefreshToken();
            return $this->requestAccessTokenByRefreshToken($refreshToken);
        }

        SsoGluuModule::$instance->sessionService->deleteAccessToken($this->clientName);

        // now we set our flag that a new request is ongoing
        $sessionId = Craft::$app->getSession()->getId();
        $lockName = 'tokenRequested: ' . $sessionId;

        $mutex = Craft::$app->getMutex();
        if (!$mutex->acquire($lockName, 15)) {
            throw new RuntimeException('Could not acquire a lock for the request token.');
        }

        $response = $this->httpClient->post(
            SsoGluuModule::REQUEST_ACCESS_TOKEN_URL,
            [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
                'auth' => [
                    $this->clientId,
                    $this->clientSecret
                ],
                'verify' => $this->verifyPeer,
                'allow_redirects' => false
            ]
        );

        if ($response->getStatusCode() === 200) {
            $responseBody = Json::decode((string)$response->getBody());
            $accessToken = GluuHelper::setAccessTokenByResponseBody($responseBody);

            if ($accessToken->validate()) {
                SsoGluuModule::$instance->sessionService->storeAccessToken($this->clientName, $accessToken);
                $mutex->release($lockName);

                return $accessToken;
            }

            $mutex->release($lockName);
            throw new AccessTokenException(ErrorHelper::getModelErrorMessages($accessToken->getErrors()));
        }

        $mutex->release($lockName);
        throw new AccessTokenException(sprintf('Getting code %s from server.', $response->getStatusCode()));
    }

    /**
     * Tries to request a new access token by refresh token
     *
     * @param string $refreshToken
     * @return AccessToken
     * @throws GuzzleException
     * @throws ServerErrorHttpException
     */
    public function requestAccessTokenByRefreshToken(string $refreshToken): AccessToken
    {
        try {
            $response = $this->httpClient->post(
                SsoGluuModule::REQUEST_ACCESS_TOKEN_URL,
                [
                    'form_params' => [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $refreshToken,
                    ],
                    'auth' => [
                        $this->clientId,
                        $this->clientSecret
                    ],
                    'verify' => $this->verifyPeer,
                    'allow_redirects' => false
                ]
            );

            if ($response->getStatusCode() === 200) {
                $responseBody = Json::decode((string)$response->getBody());
                $accessToken = GluuHelper::setAccessTokenByResponseBody($responseBody);

                if ($accessToken->validate()) {
                    SsoGluuModule::$instance->sessionService->storeAccessToken($this->clientName, $accessToken);
                    return $accessToken;
                }

                // something is wrong with the response data, so we make sure, our current user will be logged out in craft
                Craft::$app->getUser()->logout();

                Craft::error(ErrorHelper::getModelErrorMessages($accessToken->getErrors()), __METHOD__);
                throw new ServerErrorHttpException('Unexpected server error');
            }

            // when we are here, we couldn't generate a new access token. So we make sure, our current user will be logged out in craft
            Craft::$app->getUser()->logout();

            Craft::error(
                sprintf(
                    'Getting code %s from server while request access token by refresh_token. Message: %s',
                    $response->getStatusCode(),
                    $response->getReasonPhrase()
                ),
                __METHOD__
            );
            throw new ServerErrorHttpException('Unexpected server error');
        } catch (ClientException|ServerException|Exception $e) {
            // something went wrong. so we have to make sure logged-in user will be logged out.
            Craft::$app->getUser()->logout();

            Craft::error(
                sprintf(
                    'Getting code %s from server while request access token by refresh_token. Message: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                __METHOD__
            );
            throw new ServerErrorHttpException('Unexpected error');
        }
    }
}