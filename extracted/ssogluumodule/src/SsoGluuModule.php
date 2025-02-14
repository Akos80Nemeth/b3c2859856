<?php
/**
 * SSO Gluu Module module for Craft CMS 3.x
 *
 * Provides Gluu integration
 *
 * @link      https://dotsandlines.io
 * @copyright Copyright (c) 2022 dotsandlines GmbH
 */

namespace modules\ssogluumodule;

use Craft;
use craft\elements\User as UserElement;
use craft\i18n\PhpMessageSource;
use craft\web\Application;
use modules\ssogluumodule\clients\GluuClient;
use modules\ssogluumodule\services\SessionService;
use modules\userutils\UserUtilsModule;
use Throwable;
use yii\base\Event;
use yii\base\Module;
use yii\web\Cookie;
use yii\web\User;
use yii\web\UserEvent;

/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    dotsandlines GmbH
 * @package   SsoGluuModule
 * @since     1.0.0
 *
 * @property SessionService $sessionService
 */
class SsoGluuModule extends Module
{
    /** @const string */
    public const REQUEST_ACCESS_TOKEN_URL = 'oxauth/restv1/token';

    /** @const string */
    public const USER_ENDPOINT = 'identity/restv1/scim/v2/Users';

    /** @const string */
    public const USER_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:User';

    /** @const string */
    public const USER_EXTENSION_SCHEMA = 'urn:ietf:params:scim:schemas:extension:gluu:2.0:User';

    /** @const string */
    public const LIST_SCHEMA = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';

    /** @const string */
    public const USER_ACCESS_TOKEN_NAME = 'oebv_sso_portal_user_';

    /** @const string */
    public const ADMIN_ACCESS_TOKEN_NAME = 'oebv_api_admin';

    /** @const string */
    public const CROWD_SSO_COOKIE_DOMAIN = '.oebv.at';

    // Static Properties
    // =========================================================================
    /**
     * Static property that is an instance of this module class so that it can be accessed via
     * SsoGluuModule::$instance
     *
     * @var SsoGluuModule
     */
    public static SsoGluuModule $instance;

    // Public Properties
    // =========================================================================
    /**
     * @var    string Crowd SSO Cookie Key
     * @access public
     */
    public string $crowdSsoCookieKey = '';

    // Public Methods
    // =========================================================================
    /**
     * @inheritdoc
     */
    public function __construct($id, $parent = null, array $config = [])
    {
        Craft::setAlias('@modules/ssogluumodule', $this->getBasePath());
        $this->controllerNamespace = 'modules\ssogluumodule\controllers';

        $this->crowdSsoCookieKey = Craft::$app->getConfig()->getCustom()->crowdSsoCookieKey;

        // Translation category
        $i18n = Craft::$app->getI18n();
        /** @noinspection UnSafeIsSetOverArrayInspection */
        if (!isset($i18n->translations[$id]) && !isset($i18n->translations[$id . '*'])) {
            $i18n->translations[$id] = [
                'class' => PhpMessageSource::class,
                'sourceLanguage' => 'en-US',
                'basePath' => '@modules/ssogluumodule/translations',
                'forceTranslation' => true,
                'allowOverrides' => true,
            ];
        }

        // Set this as the global instance of this module class
        static::setInstance($this);

        parent::__construct($id, $parent, $config);
    }

    /**
     * Set our $instance static property to this class so that it can be accessed via
     * SsoGluuModule::$instance
     *
     * Called after the module class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     * @throws Throwable
     */
    public function init(): void
    {
        parent::init();
        self::$instance = $this;

        // Register services and variables before processing the request
        $this->registerComponents();

        Craft::$app->on(Application::EVENT_INIT, function () {
            Craft::beginProfile('checkCraftAndGluuSession', __METHOD__);

            /**
             * if user has craft session but gluu session has been expired.
             */
            $user = Craft::$app->getUser()->getIdentity();
            $accessToken = $user ? $this->sessionService->getAccessToken((string)$user->id) : null;

            Craft::beginProfile('checkValidCraftSessionNoGluuSession', __METHOD__);

            if (
                $user &&
                Craft::$app->getRequest()->isSiteRequest &&
                !Craft::$app->getRequest()->getIsLoginRequest() &&
                UserUtilsModule::$instance->userService->includeUser($user) &&
                (
                    (
                        !$accessToken &&
                        !Craft::$app->getRequest()->getCookies()->get($this->crowdSsoCookieKey)
                    ) ||
                    $accessToken->isExpired()
                )
            ) {
                if ($accessToken && $accessToken->isExpired()) {
                    $refreshToken = $accessToken->getRefreshToken();
                    $gluuClient = new GluuClient((string)$user->id);
                    $gluuClient->requestAccessTokenByRefreshToken($refreshToken);
                } else {
                    Craft::$app->getUser()->logout();
                }
            }

            Craft::endProfile('checkValidCraftSessionNoGluuSession', __METHOD__);


            /**
             * if user has no craft session but has a valid gluu session.
             */

            /*            Craft::beginProfile('checkNoCraftSessionValidGluuSession', __METHOD__);
                        if (
                            !$user &&
                            Craft::$app->getRequest()->isSiteRequest &&
                            Craft::$app->getUser()->getIsGuest() &&
                            $this->sessionService->getAccessToken(self::USER_ACCESS_TOKEN_NAME) &&
                            Craft::$app->getRequest()->getCookies()->get($this->crowdSsoCookieKey)
                        ) {
                            // We couldn't log in user in craft, so we delete the access token and remove sso cookie
                            SsoGluuModule::$instance->sessionService->deleteAccessToken(self::USER_ACCESS_TOKEN_NAME);

                            Craft::$app->getResponse()->getCookies()->remove(
                                new Cookie([
                                    'name' => $this->crowdSsoCookieKey,
                                    'domain' => self::CROWD_SSO_COOKIE_DOMAIN
                                ])
                            );
                        }

                        Craft::endProfile('checkNoCraftSessionValidGluuSession', __METHOD__);*/


            Craft::endProfile('checkCraftAndGluuSession', __METHOD__);
        });


        /**
         * Logout user from gluu server on craft logout event
         */
        Event::on(
            User::class,
            User::EVENT_BEFORE_LOGOUT,
            function (UserEvent $event) {
                /** @var UserElement $user */
                $user = $event->identity;

                if (
                    $user &&
                    UserUtilsModule::$instance->userService->includeUser($user)
                ) {
                    $this->sessionService->deleteAccessToken((string)$user->id);
                    $this->sessionService->deleteAccessToken(self::ADMIN_ACCESS_TOKEN_NAME);

                    Craft::$app->getResponse()->getCookies()->remove(
                        new Cookie([
                            'name' => $this->crowdSsoCookieKey,
                            'domain' => self::CROWD_SSO_COOKIE_DOMAIN
                        ])
                    );
                }
            }
        );

        /**
         * Logging in Craft involves using one of the following methods:
         *
         * Craft::trace(): record a message to trace how a piece of code runs. This is mainly for development use.
         * Craft::info(): record a message that conveys some useful information.
         * Craft::warning(): record a warning message that indicates something unexpected has happened.
         * Craft::error(): record a fatal error that should be investigated as soon as possible.
         *
         * Unless `devMode` is on, only Craft::warning() & Craft::error() will log to `craft/storage/logs/web.log`
         *
         * It's recommended that you pass in the magic constant `__METHOD__` as the second parameter, which sets
         * the category to the method (prefixed with the fully qualified class name) where the constant appears.
         *
         * To enable the Yii debug toolbar, go to your user account in the AdminCP and check the
         * [] Show the debug toolbar on the front end & [] Show the debug toolbar on the Control Panel
         *
         * http://www.yiiframework.com/doc-2.0/guide-runtime-logging.html
         */
        Craft::info(
            Craft::t(
                'sso-gluu-module',
                '{name} module loaded',
                ['name' => 'SSO Gluu Module']
            ),
            __METHOD__
        );
    }

    // Private Methods
    // =========================================================================
    /**
     * Registers the components
     */
    private function registerComponents(): void
    {
        $this->setComponents([
            'sessionService' => SessionService::class,
        ]);
    }
}
