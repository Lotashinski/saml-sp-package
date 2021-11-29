<?php

namespace Grsu\SamlSpService;

use Grsu\SamlSpService\Exception\InvalidSamlResponseException;
use Grsu\SamlSpService\Exception\InvalidServiceProviderMetadataException;
use Grsu\SamlSpService\Exception\ProviderSettingsException;
use Grsu\SamlSpService\Exception\SamlConfigException;
use Grsu\SamlSpService\Exception\SamlFlowException;
use Exception;
use OneLogin\Saml2\Auth as SamlAuth;
use OneLogin\Saml2\Error as SamlError;
use OneLogin\Saml2\Settings as SamlSettings;
use OneLogin\Saml2\ValidationError;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;


final class SamlService implements SamlServiceInterface
{

    private const SSO_SESSION = 'SSO_SESSION';
    private const SSO_RID = 'SSO_REQUEST_ID';
    private const SSO_USER = 'SSO_USER';


    private array $packageSettings;
    private SamlSettings $samlSetting;
    private SamlAuth $auth;
    private SessionInterface $session;
    private LoggerInterface $logger;


    /**
     * @throws SamlConfigException|ProviderSettingsException
     */
    public function __construct(
        array            $settings,
        SessionInterface $session,
        LoggerInterface  $logger
    )
    {
        $this->logger = $logger;
        $this->setPackageSettingsOrThrowIfError($settings);

        try {
            $this->auth = new SamlAuth($settings['providers_setting']);
            $this->samlSetting = $this->auth->getSettings();
        } catch (SamlError $se) {
            $this->logger->error("Any settings parameter is invalid. Check 'providers_setting'.");
            $this->logger->error($se->getMessage());
            throw new ProviderSettingsException("Initialization exception.", $se);
        } catch (Exception $e) {
            $this->logger->error("Settings is incorrectly supplied. Check 'providers_setting'.");
            $this->logger->error($e->getMessage());
            throw new SamlConfigException("Invalid setting data. Check 'providers_setting'.",);
        }

        $this->session = $session;
    }

    /**
     * @throws InvalidServiceProviderMetadataException
     */
    public function getMetadata(): string
    {
        try {
            $this->logger->debug("Configure metadata");
            $meta = $this->samlSetting->getSPMetadata(false, $this->packageSettings['valid_until']);
            $errors = $this->samlSetting->validateMetadata($meta);
            if ($errors) {
                $message = 'Invalid SP metadata: ' . implode(', ', $errors);
                $this->logger->error($message);
                throw new InvalidServiceProviderMetadataException($message);
            }
            return $meta;
        } catch (InvalidServiceProviderMetadataException $invalidSamlResponseException) {
            throw $invalidSamlResponseException;
        } catch (Exception $e) {
            throw new InvalidServiceProviderMetadataException('Invalid Sp metadata.', $e);
        }
    }


    /**
     * @throws SamlFlowException
     */
    public function createLoginFlowAndReturnRedirectUrl(): string
    {
        $this->logger->debug("Create login flow.");

        try {
            $ssoLoginUrl = $this->auth->login(null, [], false, false, true);
            $requestId = $this->auth->getLastRequestID();
            $this->setRequestId($requestId);
            return $ssoLoginUrl;
        } catch (SamlError $se) {
            $message = 'Internal package exception';
            $this->logger->error($message);
            throw new SamlFlowException($message, $se);
        }
    }


    /**
     * @throws SamlFlowException
     * @throws InvalidSamlResponseException
     */
    public function completeLoginFlow(): SamlUser
    {
        $this->logger->debug("Complete login flow.");

        $requestId = $this->getRequestIdOrThrow();
        try {
            $this->auth->processResponse($requestId);
        } catch (SamlError $se) {
            $message = 'Internal package exception.';
            $this->logger->error($message);
            throw new SamlFlowException($message, $se);
        } catch (ValidationError $e) {
            $message = 'Response validation error.';
            $this->logger->error($message);
            throw new InvalidSamlResponseException($message, $e);
        }

        $errors = $this->auth->getErrors();
        if (!empty($errors)) {
            $message = 'Invalid SamlResponse: ' . implode(', ', $errors);
            $this->logger->error($message);
            throw new InvalidSamlResponseException($message);
        }

        $this->purgeRequestId();

        if (!$this->auth->isAuthenticated()) {
            $message = 'User is not authenticated';
            $this->logger->error($message);
            throw new SamlFlowException($message);
        }

        $session = $this->parseSamlSessionFromResponse();
        $user = $this->parseSamlUserFromResponse();

        $this->setSessionData(
            samlSession: $session,
            samlUser: $user
        );

        return $user;
    }

    /**
     * @throws SamlFlowException
     */
    public function createOrConfirmLogoutFlow()
    {
        if (isset($_GET) && isset($_GET['SAMLRequest'])) {
            $this->commitLogoutRequestFlow();
        } else if (isset($_GET) && isset($_GET['SAMLResponse'])) {
            $this->commitLogoutResponseFlow();
        } else {
            $this->initLogoutFlow();
        }
    }

    /**
     * @throws SamlConfigException
     */
    private function setPackageSettingsOrThrowIfError(array $settings)
    {
        $this->logger->debug("Check settings");

        if (!isset($settings['response'])) {
            $message = "Invalid setting data! Block 'response' missing.";
            $this->logger->error($message);
            throw new SamlConfigException($message);
        }

        $availableKeys = ['user_uid', 'user_login', 'user_email',];
        $settingsKeys = array_keys($settings['response']);
        $diff = array_diff($availableKeys, $settingsKeys);
        if ($diff) {
            $missing = implode(', ', $diff);
            $message = "Settings error! The following parameters are missing in the block 'response': $missing";
            $this->logger->error($message);
            throw new SamlConfigException($message);
        }

        if (!isset($settings['valid_until'])) {
            throw new SamlConfigException("Invalid setting data. Key 'valid_until' missing.");
        }

        if (!is_int($settings['valid_until'])) {
            $message = "Invalid setting data. 'valid_until is " . gettype($settings['valid_until']) . ". But int expired.";
            $this->logger->error($message);
            throw new SamlConfigException($message);
        }

        if (!isset($settings['providers_setting'])) {
            throw new SamlConfigException("Invalid setting data. Block 'providers_setting' missing.",);
        }

        $this->packageSettings = $settings;

        $this->logger->debug("Settings ok");
    }

    private function setRequestId(string $requestIdentified): void
    {
        $this->session->set(self::SSO_RID, $requestIdentified);
    }

    /**
     * @throws SamlFlowException
     */
    private function getRequestIdOrThrow(): string
    {
        return $this->session->get(self::SSO_RID) ?? throw new SamlFlowException('Current session id is empty');
    }

    private function purgeRequestId(): void
    {
        $this->session->remove(self::SSO_RID);
    }

    private function setSessionData(SamlSession $samlSession, SamlUser $samlUser): void
    {
        $this->session->set(self::SSO_SESSION, $samlSession);
        $this->session->set(self::SSO_USER, $samlUser);
    }

    private function parseSamlSessionFromResponse(): SamlSession
    {
        $auth = $this->auth;

        return new SamlSession(
            samlUserdata: $auth->getAttributes(),
            samlNameId: $auth->getNameId(),
            samlNameIdFormat: $auth->getNameIdFormat(),
            samlNameIdNameQualifier: $auth->getNameIdNameQualifier(),
            samlNameIdSPNameQualifier: $auth->getNameIdSPNameQualifier(),
            samlSessionIndex: $auth->getSessionIndex()
        );
    }

    /**
     * @throws InvalidSamlResponseException
     */
    private function parseSamlUserFromResponse(): SamlUser
    {
        $userData = $this->auth->getAttributes();
        $sessionId = $this->auth->getSessionIndex();

        $responseSettings = $this->packageSettings['response'];

        if (!isset($userData[$responseSettings['user_uid']])) {
            $message = "'${$responseSettings['user_uid']}' missing in IdP response or 'settings.response' invalid";
            $this->logger->error($message);
            throw new InvalidSamlResponseException($message);
        }

        if (!isset($userData[$responseSettings['user_login']])) {
            $message = "'${$responseSettings['user_login']}' missing in IdP response or 'settings.response' invalid";
            $this->logger->error($message);
            throw new InvalidSamlResponseException($message);
        }

        if (!isset($userData[$responseSettings['user_email']])) {
            $message = "'${$responseSettings['user_email']}' missing in IdP response or 'settings.response' invalid";
            $this->logger->error($message);
            throw new InvalidSamlResponseException($message);
        }

        $uid = $userData[$responseSettings['user_uid']][0];
        $login = $userData[$responseSettings['user_login']][0];
        $mail = $userData[$responseSettings['user_email']][0];

        return new SamlUser(
            providerUniqueId: $uid,
            sessionId: $sessionId,
            userLogin: $login,
            userEmail: $mail
        );
    }


    /**
     * @throws SamlFlowException
     */
    private function initLogoutFlow()
    {
        $this->logger->debug("Init logout flow.");

        try {
            $this->auth->logout();
        } catch (SamlError $se) {
            $message = 'Error in initialization logout flow';
            $this->logger->error($message);
            throw new SamlFlowException($message, $se);
        }
    }


    /**
     * @throws SamlFlowException
     */
    private function commitLogoutRequestFlow(): void
    {
        $this->logger->debug("Commit logout flow from IdP.");
        $this->purgeSamlData();
        $this->initProcessSlo();
    }

    /**
     * @throws SamlFlowException
     */
    private function commitLogoutResponseFlow(): void
    {
        $this->logger->debug("Commit logout flow from User.");
        $this->initProcessSlo();
        $this->purgeSamlData();
    }

    /**
     * @throws SamlFlowException
     */
    private function initProcessSlo(): void
    {
        try {
            $this->logger->debug("Saml process SLO...");
            $this->auth->processSLO();
            $this->logger->debug("Check errors...");
            $errors = $this->auth->getErrors();
            if (!empty($errors)) {
                throw new SamlError('Logout flow error: ' . implode(', ', $errors));
            }
        } catch (SamlError $se) {
            $message = "SLO error. " . $se->getMessage();
            $this->logger->error($message);
            throw new SamlFlowException($message, $se);
        }
    }

    private function purgeSamlData()
    {
        $this->logger->debug('Remove user sso session from app session storage');
        $this->session->remove(self::SSO_USER);
        $this->session->remove(self::SSO_SESSION);
        $this->session->remove(self::SSO_RID);
    }

}