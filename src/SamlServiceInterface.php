<?php

namespace Grsu\SamlSpService;

use Grsu\SamlSpService\Exception\InvalidSamlResponseException;
use Grsu\SamlSpService\Exception\InvalidServiceProviderMetadataException;
use Grsu\SamlSpService\Exception\SamlFlowException;


interface SamlServiceInterface
{
    /**
     * @throws InvalidServiceProviderMetadataException
     */
    public function getMetadata(): string;

    /**
     * @throws SamlFlowException
     */
    public function createLoginFlowAndReturnRedirectUrl(): string;

    /**
     * @throws SamlFlowException
     * @throws InvalidSamlResponseException
     */
    public function completeLoginFlow(): SamlUser;

    /**
     * @throws SamlFlowException
     */
    public function createOrConfirmLogoutFlow();

}