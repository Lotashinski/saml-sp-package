# SamlPackage

______________________

# Installation

______________________

Install the latest version with

```bash
$ composer require lotashinski/saml-sp-package
```

# Basic Usage

_____________________

## 1. Generate cert

You can use openssl to generate keys and certificate:

```bash
$ openssl genrsa -out encryptKey.pem 4096
$ openssl req -new -x509 -key encryptKey.pem -out encryptionCert.cer -days 3650
```

## 2. Create config file

Create a file in project settings. For Symfony: ```./config```.

```yaml
## config/sso_saml.yaml

# Indicates user data keys in the IdP response
response:
  user_uid:   # user unique id in IdP
  user_login: # user login
  user_email: # user email

# Valid until (Unix time)
valid_until: 1672520400

# The block is passed to the package https://github.com/onelogin/php-sam 
providers_setting:
  strict: true
  debug: false
  baseurl: null

  sp:
    entityId: # unique sp id (allow domain)

    assertionConsumerService:
      url: # example https://<sp_domain>/app/saml/login
      binding: # example urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST

    singleLogoutService:
      url: # example https://<sp_domain>/app/saml/logout
      binding: # example urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect

    NameIDFormat: # example urn:oasis:names:tc:SAML:2.0:nameid-format:transient

    x509cert:
    # copy from encryptKey.pem 
    privateKey:
    # copy from encryptKey.cer 

  idp:
    entityId: # example https://<idp_domain>/simplesaml/saml2/idp/metadata.php

    singleSignOnService:
      url: # example https://<idp_domain>/simplesaml/saml2/idp/SSOService.php
      binding: # urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect

    singleLogoutService:
      url: # example https://<idp_domain>/simplesaml/saml2/idp/SingleLogoutService.php

      binding: # urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect

    x509cert:
    # Identity provider cert


```

## 3. Configure in app

### Configure from code

```php
<?php

use Grsu\SamlSpService\SamlServiceFactory;
use Grsu\SamlSpService\SamlServiceInterface;

const CONFIG_FILE_PATH = __DIR__ . "/config/sso_saml.yaml"

// create service from factory
$samlServiceFactory = new SamlServiceFactory($loggerInterface, $requestStack);
$samlService = $samlServiceFactory->createServiceFromConfigFile(CONFIG_FILE_PATH);
```

### Or if you use Symfony

Can use symfony autowiring to manage service

```yaml
# config/services.yaml
services:

  // ...

  SamlService\SamlServiceFactory:
    tags:
      - { name: monolog.logger, channel: SamlService } # Configure logger configure channel
  SamlService\SamlServiceInterface:
    factory: '@SamlService\SamlServiceFactory'
    arguments:
      $configFilePath: '%kernel.project_dir%/config/sso_saml.yaml' # Path to config
```

Code example:

```php
<?php

namespace App\Controller;

use Grsu\SamlSpService\Exception\InvalidServiceProviderMetadataException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{

    private SamlServiceInterface $samlService;
    private EventDispatcherInterface $eventDispatcher;


    public function __construct(
        SamlServiceInterface     $samlService,
        EventDispatcherInterface $eventDispatcher
    )
    {
        $this->samlService = $samlService;
        $this->eventDispatcher = $eventDispatcher;
    }


    /**
     * @throws InvalidServiceProviderMetadataException
     */
    #[Route('/saml/metadata', name: 'saml_auth', methods: ['GET'])]
    public function metadata(): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text-xml');
        $response->setContent($this->samlService->getMetadata());
        return $response;
    }
    
    /**
     * @throws SamlFlowException
     */
    #[Route('/saml/login', name: 'saml_start_login_flow', methods: ['GET'])]
    public function startLoginFlow(): Response
    {
        return $this->redirect($this->samlService->createLoginFlowAndReturnRedirectUrl());
    }

    /**
     * @throws InvalidSamlResponseException
     * @throws SamlFlowException
     */
    #[Route('/saml/login', name: 'saml_confirm_login_flow', methods: ['POST'])]
    public function confirmLoginFlow(): Response
    {
        $user = $this->samlService->completeLoginFlow();
        
        // ...
        
        return $this->json("success");
    }

    /**
     * @throws SamlFlowException
     */
    #[Route('/saml/logout', name: 'saml_logout_flow', methods: ['GET'])]
    public function configureOrReceiveLogoutFlow(): Response
    {
        $this->samlService->createOrConfirmLogoutFlow();
        
        // ...
        
        return $this->json("user logout");
    }
    
}
```
