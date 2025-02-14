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
use craft\helpers\App;
use craft\helpers\Json;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use modules\ssogluumodule\helpers\CrowdHelper;
use yii\web\ServerErrorHttpException;

/**
 * Crowd Client
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
 *
 */
class CrowdClient extends Component
{
    /** @const string */
    public const REQUEST_URI = '/crowd/rest/usermanagement/latest/session?validate-password=false';

    /**
     * @var string application user
     */
    private string $applicationUser;

    /**
     * @var string application password
     */
    private string $applicationPassword;

    /**
     * @var Client
     */
    private Client $httpClient;

    public function __construct()
    {
        parent::__construct();
        $this->applicationUser = App::env('CROWD_APPLICATION_USER');
        $this->applicationPassword = App::env('CROWD_APPLICATION_PASSWORD');
        $this->httpClient = new Client([
            'base_uri' => App::env('CROWD_SERVER_PATH') . ':' . App::env('CROWD_SERVER_PORT'),
        ]);
    }


    /**
     * Get Crowd token by userKlebrID
     *
     * @param string $userKlebrID
     * @param string $password
     * @return string|null
     * @throws GuzzleException
     * @throws ServerErrorHttpException
     */
    public function getCrowdToken(string $userKlebrID, string $password = ''): ?string
    {
        $data = CrowdHelper::createPayload($userKlebrID, $password);

        try {
            $response = $this->httpClient->post(
                self::REQUEST_URI,
                [
                    'headers' => [
                        'Authorization' => sprintf('Basic %s', base64_encode($this->applicationUser . ':' . $this->applicationPassword)),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ],
                    'json' => $data,
                    'http_errors' => false
                ]
            );

            if ($response->getStatusCode() === 201) {
                $responseBody = Json::decode((string)$response->getBody());

                if (isset($responseBody['token']) && $responseBody['token']) {
                    return $responseBody['token'];
                }
            }

            Craft::error(
                sprintf(
                    'Getting code %s from server while trying to get crowd token. Message: %s',
                    $response->getStatusCode(),
                    $response->getReasonPhrase()
                ),
                __METHOD__
            );

            return null;
        } catch (ClientException|ServerException|Exception $e) {
            Craft::error(
                sprintf(
                    'Getting code %s from server while trying to get crowd token. Message: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                __METHOD__
            );
            throw new ServerErrorHttpException('Unexpected error');
        }
    }
}