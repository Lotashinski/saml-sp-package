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
    private ?SamlServiceInterface $samlService = null;
    private string|array $configOrPath;


    public function __construct(
        LoggerInterface $logger,
        RequestStack    $requestStack,
        string|array    $configOrPath
    )
    {
        $this->configOrPath = $configOrPath;
        $this->logger = $logger;
        $this->session = $requestStack->getSession();
    }


    /**
     * @throws SamlConfigException
     * @throws ProviderSettingsException
     */
    public function __invoke(): SamlService
    {
        return $this->createServiceFromConfigFile();
    }


    /**
     * @throws SamlConfigException
     * @throws ProviderSettingsException
     */
    public function createServiceFromConfigFile(): SamlServiceInterface
    {
        /**
         * @var array
         */
        $settings = [];

        if (is_array($this->configOrPath)) {
            $this->logger->debug('Load saml config from array');
            $settings = $this->configOrPath;
        } else {
            $this->logger->debug('Load saml config from file');
            $settings = $this->loadConfigFromFile($this->configOrPath);
        }

        $this->logger->debug('Load session');

        try {
            if ($this->samlService === null) {
                $this->samlService = new SamlService($settings, $this->session, $this->logger);
            }
            return $this->samlService;
        } catch (SamlConfigException | ProviderSettingsException $configException) {
            $message = "Configure sso package fail from $this->configOrPath fail. Check configs.";
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