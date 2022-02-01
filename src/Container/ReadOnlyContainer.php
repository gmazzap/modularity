<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Container;

use Inpsyde\Modularity\Event;
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
     * @var Event\Dispatcher
     */
    private $dispatcher;

    /**
     * ReadOnlyContainer constructor.
     *
     * @param array<string, callable(ContainerInterface $container):mixed> $services
     * @param array<string, bool> $factoryIds
     * @param array<string, array<callable(mixed, ContainerInterface $container):mixed>> $extensions
     * @param ContainerInterface[] $containers
     */
    public function __construct(
        array $services,
        array $factoryIds,
        array $extensions,
        array $containers,
        ?Event\Dispatcher $dispatcher = null
    ) {
        $this->services = $services;
        $this->factoryIds = $factoryIds;
        $this->extensions = $extensions;
        $this->containers = $containers;
        $this->dispatcher = $dispatcher ?? new Event\Dispatcher();
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
            $isFactory = isset($this->factoryIds[$id]);

            $beforeEvent = Event\BeforeServiceResolved::new($id, $this, false, $isFactory);
            $this->dispatcher->dispatch($beforeEvent);

            $service = $this->services[$id]($this);
            $resolved = $this->resolveExtensions($id, $service);

            if (!isset($this->factoryIds[$id])) {
                $this->resolvedServices[$id] = $resolved;
                unset($this->services[$id]);
            }

            $event = Event\AfterServiceResolved::new($id, $resolved, $this, false, $isFactory);
            $this->dispatcher->dispatch($event);

            return $resolved;
        }

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                $beforeEvent = Event\BeforeServiceResolved::new($id, $this, true, false);
                $this->dispatcher->dispatch($beforeEvent);

                $service = $this->resolveExtensions($id, $container->get($id));

                $event = Event\AfterServiceResolved::new($id, $service, $this, true, false);
                $this->dispatcher->dispatch($event);

                return $service;
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
