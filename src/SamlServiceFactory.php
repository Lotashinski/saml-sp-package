<?php

namespace Grsu\SamlSpService;

use Grsu\SamlSpService\Exception\ProviderSettingsException;
use Grsu\SamlSpService\Exception\SamlConfigException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Yaml\Yaml;


final class SamlServiceFactory
{

    private LoggerInterface $logger;
    private SessionInterface $session;


    public function __construct(
        LoggerInterface  $logger,
        RequestStack $requestStack
    )
    {
        $this->logger = $logger;
        $this->session = $requestStack->getSession();
    }


    /**
     * @throws SamlConfigException
     * @throws ProviderSettingsException
     */
    public function __invoke(string $configFilePath): SamlService
    {
        return $this->createServiceFromConfigFile($configFilePath);
    }


    /**
     * @throws SamlConfigException
     * @throws ProviderSettingsException
     */
    public function createServiceFromConfigFile(
        string $configFilePath,
    ): SamlServiceInterface
    {
        $this->logger->debug('Load saml config from yaml');
        $settings = $this->loadConfigFromFile($configFilePath);

        $this->logger->debug('Load session');

        try {
            return new SamlService($settings, $this->session, $this->logger);
        } catch (SamlConfigException | ProviderSettingsException $configException) {
            $message = "Configure sso package fail from ${configFilePath} fail. Check configs.";
            $this->logger->error($message);
            throw $configException;
        }
    }


    private function loadConfigFromFile(string $path): array
    {
        $this->logger->debug('Read settings from yaml');
        return Yaml::parseFile($path);
    }

}