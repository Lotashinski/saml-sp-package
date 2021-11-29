<?php

namespace Grsu\SamlSpService;

final class SamlUser
{

    private string $providerUniqueId;
    private string $userLogin;
    private string $userEmail;
    private string $sessionId;


    public function __construct(
        string $providerUniqueId,
        string $sessionId,
        string $userLogin,
        string $userEmail
    )
    {
        $this->providerUniqueId = $providerUniqueId;
        $this->sessionId = $sessionId;
        $this->userLogin = $userLogin;
        $this->userEmail = $userEmail;
    }


    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getProviderUniqueId(): string
    {
        return $this->providerUniqueId;
    }

    public function getUserLogin(): string
    {
        return $this->userLogin;
    }

    public function getUserEmail(): string
    {
        return $this->userEmail;
    }

}