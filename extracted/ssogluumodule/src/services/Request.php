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

use craft\base\Component;
use craft\helpers\App;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Request
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
 * @property-read array $headers
 */
class Request extends Component
{
    /** @var array */
    private array $requestHeaders;

    /** @var Client */
    private Client $client;


    public function __construct()
    {
        parent::__construct();
        $this->client = new Client([
            'base_uri' => App::env('GLUU_BASE_URL'),
        ]);
    }

    /**
     * @param string $requestUri
     * @param array $options
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function get(string $requestUri, array $options = []): ResponseInterface
    {
        return $this->client->request('GET', $requestUri, $options);
    }

    /**
     * @param string $requestUri
     * @param array $options
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function post(string $requestUri, array $options = []): ResponseInterface
    {
        return $this->client->request('POST', $requestUri, $options);
    }

    /**
     * @param string $requestUri
     * @param array $options
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function patch(string $requestUri, array $options = []): ResponseInterface
    {
        return $this->client->request('PATCH', $requestUri, $options);
    }

    /**
     * @param string $requestUri
     * @param array $options
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function put(string $requestUri, array $options = []): ResponseInterface
    {
        return $this->client->request('PUT', $requestUri, $options);
    }

    /**
     * @param string $requestUri
     * @param array $options
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function delete(string $requestUri, array $options = []): ResponseInterface
    {
        return $this->client->request('DELETE', $requestUri, $options);
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return void
     */
    public function setHeader(string $key, string $value): void
    {
        $this->requestHeaders[$key] = $value;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->requestHeaders;
    }
}
