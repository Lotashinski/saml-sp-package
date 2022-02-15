<?php
declare(strict_types=1);

namespace Grsu\SamlSpService;

interface StorageInterface
{
    function has(string $key): bool;

    function set(string $key, mixed $value): void;

    function get(string $key): mixed;

    function remove(string $key): void;
}