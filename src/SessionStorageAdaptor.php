<?php
declare(strict_types=1);

namespace Grsu\SamlSpService;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionStorageAdaptor implements StorageInterface
{

    public function __construct(
        private SessionInterface $session
    )
    {
    }


    function has(string $key): bool
    {
        return $this->session->has($key);
    }

    function set(string $key, mixed $value): void
    {
        $this->session->set($key, $value);
    }

    function get(string $key): mixed
    {
        return $this->session->get($key);
    }

    function remove(string $key): void
    {
        $this->session->remove($key);
    }
}