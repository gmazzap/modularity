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
            $this->dispatchEvent(Event\BeforeServiceResolved::class, $id, $isFactory);

            $service = $this->services[$id]($this);
            $resolved = $this->resolveExtensions($id, $service);

            if (!isset($this->factoryIds[$id])) {
                $this->resolvedServices[$id] = $resolved;
                unset($this->services[$id]);
            }

            $this->dispatchEvent(Event\AfterServiceResolved::class, $id, $isFactory, $resolved);

            return $resolved;
        }

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                $this->dispatchEvent(Event\BeforeServiceResolved::class, $id, null);

                $resolved = $this->resolveExtensions($id, $container->get($id));

                $this->dispatchEvent(Event\AfterServiceResolved::class, $id, null, $resolved);

                return $resolved;
            }
        }

        $error = new class ("Service with ID {$id} not found.")
            extends \Exception
            implements NotFoundExceptionInterface {
        };

        /** @var Event\ServiceNotResolved $event */
        $event = $this->dispatchEvent(Event\ServiceNotResolved::class, $id, null, $error);
        if (!$event->hasService()) {
            throw $error;
        }

        $resolved = $event->service();
        $this->resolvedServices[$id] = $resolved;

        return $resolved;
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

    /**
     * @param class-string<Event\ServiceEvent> $class
     * @param string $serviceId
     * @param bool|null $isFactory
     * @param mixed $serviceOrError
     * @return Event\ServiceEvent
     */
    private function dispatchEvent(
        string $class,
        string $serviceId,
        ?bool $isFactory,
        $serviceOrError = null
    ): Event\ServiceEvent {

        if ($class === Event\ServiceNotResolved::class) {
            /** @var \Throwable $serviceOrError */
            $event = new Event\ServiceNotResolved($serviceOrError, $serviceId, $this);
            $this->dispatcher->dispatch($event);

            return $event;
        }

        switch (true) {
            case ($isFactory === true):
                $type = ($class === Event\BeforeServiceResolved::class)
                    ? Event\ServiceEvent::BEFORE_RESOLVED_FACTORY
                    : Event\ServiceEvent::AFTER_RESOLVED_FACTORY;
                break;
            case ($isFactory === null):
                $type = ($class === Event\BeforeServiceResolved::class)
                    ? Event\ServiceEvent::BEFORE_RESOLVED_EXTERNAL
                    : Event\ServiceEvent::AFTER_RESOLVED_EXTERNAL;
                break;
            default:
                $type = ($class === Event\BeforeServiceResolved::class)
                    ? Event\ServiceEvent::BEFORE_RESOLVED
                    : Event\ServiceEvent::AFTER_RESOLVED;
                break;
        }

        $event = ($class === Event\BeforeServiceResolved::class)
            ? new Event\BeforeServiceResolved($type, $serviceId, $this)
            : new Event\AfterServiceResolved($type, $serviceId, $serviceOrError, $this);

        $this->dispatcher->dispatch($event);

        return $event;
    }
}
