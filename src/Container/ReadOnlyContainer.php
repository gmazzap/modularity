<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class ReadOnlyContainer implements ContainerInterface
{
    /**
     * @var array<string, callable(\Psr\Container\ContainerInterface $container):mixed>
     */
    private $services;

    /**
     * @var array<string, bool>
     */
    private $factoryIds;

    /**
     * @var array<string, array<callable(mixed, ContainerInterface $container):mixed>>
     */
    private $extensions;

    /**
     * Resolved factories.
     *
     * @var array<string, mixed>
     */
    private $resolvedServices = [];

    /**
     * @var ContainerInterface[]
     */
    private $containers;

    /**
     * @var ServiceListeners
     */
    private $listeners;

    /**
     * ReadOnlyContainer constructor.
     *
     * @param array<string, callable(ContainerInterface $container):mixed> $services
     * @param array<string, bool> $factoryIds
     * @param array<string, array<callable(mixed, ContainerInterface $container):mixed>> $extensions
     * @param ContainerInterface[] $containers
     * @param ServiceListeners|null $listeners
     */
    public function __construct(
        array $services,
        array $factoryIds,
        array $extensions,
        array $containers,
        ?ServiceListeners $listeners = null
    ) {
        $this->services = $services;
        $this->factoryIds = $factoryIds;
        $this->extensions = $extensions;
        $this->containers = $containers;
        $this->listeners = $listeners ?? new ServiceListeners();
    }

    /**
     * @param string $id
     *
     * @return mixed
     */
    public function get($id)
    {
        assert(is_string($id));

        if (array_key_exists($id, $this->resolvedServices)) {
            return $this->resolvedServices[$id];
        }

        if (array_key_exists($id, $this->services)) {
            $this->listeners->updateBeforeGet($id);

            $service = $this->services[$id]($this);
            $resolved = $this->resolveExtensions($id, $service);

            if (!isset($this->factoryIds[$id])) {
                $this->resolvedServices[$id] = $resolved;
                unset($this->services[$id]);
            }

            $this->listeners->updateAfterGet($id, $resolved);

            return $resolved;
        }

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                $this->listeners->updateBeforeGet($id);
                $resolved = $this->resolveExtensions($id, $container->get($id));
                $this->listeners->updateAfterGet($id, $resolved);

                return $resolved;
            }
        }

        throw new class ("Service with ID {$id} not found.")
            extends \Exception
            implements NotFoundExceptionInterface {
        };
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function has($id)
    {
        assert(is_string($id));

        if (array_key_exists($id, $this->services)) {
            return true;
        }

        if (array_key_exists($id, $this->resolvedServices)) {
            return true;
        }

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $id
     * @param mixed $service
     *
     * @return mixed
     */
    private function resolveExtensions(string $id, $service)
    {
        if (!isset($this->extensions[$id])) {
            return $service;
        }

        foreach ($this->extensions[$id] as $extender) {
            $service = $extender($service, $this);
        }

        return $service;
    }
}
