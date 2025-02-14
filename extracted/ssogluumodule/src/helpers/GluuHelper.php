<?php
/**
 * SSO Gluu Module module for Craft CMS 3.x
 *
 * Provides Gluu integration
 *
 * @link      https://dotsandlines.io
 * @copyright Copyright (c) 2022 dotsandlines GmbH
 */

namespace modules\ssogluumodule\helpers;

use craft\elements\User;
use craft\helpers\Json;
use modules\ssogluumodule\models\AccessToken;
use modules\ssogluumodule\models\CustomAttributes;
use modules\ssogluumodule\models\GluuUser;
use modules\ssogluumodule\models\Meta;
use modules\ssogluumodule\models\Name;
use modules\ssogluumodule\SsoGluuModule;
use modules\userutils\controllers\OnboardingController;
use modules\userutils\models\UserRegisterModel;
use modules\userutils\models\UserUpdateModel;
use Psr\Http\Message\ResponseInterface;

/**
 * Gluu Helper
 *
 * @author    dotsandlines GmbH
 * @package   SsoGluuModule
 * @since     1.0.0
 */
class GluuHelper
{
    public static function createFromCraftUser(User $user): array
    {
        $gluuUser = new GluuUser();
        $name = new Name();

        $name->givenName = $user->firstName;
        $name->familyName = $user->lastName;
        $name->formatted = $user->fullName;

        $gluuUser->name = $name;
        $gluuUser->userName = $user->email;
        $gluuUser->displayName = $user->fullName;
        $gluuUser->emails = [
            ['value' => $user->email]
        ];

        $gluuUser->active = $user->enabled && !$user->pending && !$user->suspended && !$user->locked;

        $userGroupHandle = isset($user->getGroups()[0]->handle) ? $user->getGroups()[0]->handle : 'private';
        $customAttributes = new CustomAttributes();
        $customAttributes->oebvPeType = [self::mapUserGroupToPeType($userGroupHandle)];
        $customAttributes->oebvCheckPeType = $user->userVerificationStatus->value === OnboardingController::VERIFICATION_COMPLETED;
        $customAttributes->oebvKlebrId = $user->userKlebrID;

        $userSchools = $user->userSchools->all();
        if ($userSchools) {
            foreach ($userSchools as $userSchool) {
                $school = $userSchool->school->status('enabled')->one();
                if (!$school) {
                    continue;
                }
                $schoolCodesInt[] = (int)$school->schoolCode;
            }

            $customAttributes->oebvSchoolCode = $schoolCodesInt ?? null;
        }

        $gluuUserAsArray = $gluuUser->toArray();
        $gluuUserAsArray[SsoGluuModule::USER_EXTENSION_SCHEMA] = array_filter($customAttributes->toArray());
        return array_filter($gluuUserAsArray);
    }

    /**
     * Prepares user data for Gluu
     * @param User $user
     * @param UserUpdateModel $model
     * @return array
     */
    public static function prepareUserDataForGluu(User $user, UserUpdateModel $model): array
    {
        $userData = [];

        if ($user->firstName !== $model->firstName) {
            $userData['name']['givenName'] = $model->firstName;
        }

        if ($user->lastName !== $model->lastName) {
            $userData['name']['familyName'] = $model->lastName;
        }

        if ($model->schools) {
            $schoolCodes = array_column($model->schools, 'schoolCode');
            $schoolCodesInt = array_map(
                function ($value) {
                    return (int)$value;
                },
                $schoolCodes
            );
            $userData[SsoGluuModule::USER_EXTENSION_SCHEMA]['oebvSchoolCode'] = $schoolCodesInt;
        }

        return $userData;
    }

    /**
     * Prepares PeType and checkPeType for Gluu
     * @param User $user
     * @return array
     */
    public static function preparePeTypeForGluu(User $user): array
    {
        $payload = [];

        if ($user->userTemporaryUserGroupHandle) {
            $oebvCheckPeType = false;

            if (in_array($user->userTemporaryUserGroupHandle, OnboardingController::ROLES_REQUIRE_VERIFICATION, true)) {
                $oebvCheckPeType = true;
            }

            $payload[SsoGluuModule::USER_EXTENSION_SCHEMA] = [
                'oebvPeType' => [self::mapUserGroupToPeType($user->userTemporaryUserGroupHandle)],
                'oebvCheckPeType' => $oebvCheckPeType,
            ];
        }

        return $payload;
    }

    /**
     * Maps usergroup to PeType of Gluu
     * @param string $userTemporaryUserGroupHandle
     * @return string
     */
    public static function mapUserGroupToPeType(string $userTemporaryUserGroupHandle): string
    {
        return match ($userTemporaryUserGroupHandle) {
            'oebvEmployee' => 'oebv_employee',
            'teacher' => 'teacher',
            'parent' => 'parent',
            'student' => 'student',
            'pupilUnder14' => 'pupil_under_14',
            'pupilFrom14' => 'pupil_over_14',
            default => 'private_person',
        };
    }

    /**
     * Maps PeType of Gluu to usergroup
     * @param string $peType
     * @return string
     */
    public static function mapPeTypeToUserGroup(string $peType): string
    {
        return match ($peType) {
            'oebv_employee' => 'oebvEmployee',
            'teacher' => 'teacher',
            'parent' => 'parent',
            'student' => 'student',
            'pupil_under_14' => 'pupilUnder14',
            'pupil_over_14' => 'pupilFrom14',
            default => 'private',
        };
    }

    /**
     * Prepares user payload for creating a new user in gluu
     * @param UserRegisterModel $userData
     * @return array
     */
    public static function prepareCreateUserPayload(UserRegisterModel $userData): array
    {
        $payload = [];
        $payload['schemas'] = [SsoGluuModule::USER_SCHEMA];
        $payload['userName'] = $userData->email;
        $payload['password'] = $userData->password;
        $payload['emails'] = [
            ['value' => $userData->email]
        ];

        $payload[SsoGluuModule::USER_EXTENSION_SCHEMA] = [
            'oebvExtSystem' => 'Craft',
            'oebvPeType' => ['private_person']
        ];

        return $payload;
    }

    /**
     * Set access token by response body
     * @param $responseBody
     * @return AccessToken
     */
    public static function setAccessTokenByResponseBody($responseBody): AccessToken
    {
        $accessToken = new AccessToken();
        $accessToken->accessToken = $responseBody['access_token'] ?? null;
        $accessToken->tokenType = $responseBody['token_type'] ?? null;
        $accessToken->expiresIn = $responseBody['expires_in'] ?? null;
        $accessToken->refreshToken = $responseBody['refresh_token'] ?? null;
        $accessToken->scope = $responseBody['scope'] ?? null;
        $accessToken->idToken = $responseBody['id_token'] ?? null;
        return $accessToken;
    }

    /**
     * Create Gluu user from gluu api response
     * @param ResponseInterface $response
     * @return GluuUser
     */
    public static function createGluuUser(ResponseInterface $response): GluuUser
    {
        $responseBody = Json::decode((string)$response->getBody());

        $gluuUser = new GluuUser();

        if (isset($responseBody['meta'])) {
            $meta = new Meta();
            $meta->attributes = $responseBody['meta'];
        }

        if (isset($responseBody['name'])) {
            $name = new Name();
            $name->attributes = $responseBody['name'];
        }

        if (isset($responseBody[SsoGluuModule::USER_EXTENSION_SCHEMA])) {
            $customAttributes = new CustomAttributes();
            $customAttributes->attributes = $responseBody[SsoGluuModule::USER_EXTENSION_SCHEMA];
        }

        $gluuUser->meta = $meta ?? null;
        $gluuUser->name = $name ?? null;
        $gluuUser->customAttributes = $customAttributes ?? null;

        $gluuUser->attributes = $responseBody;

        return $gluuUser;
    }
}
