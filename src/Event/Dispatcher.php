<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

class Dispatcher implements EventDispatcherInterface
{
    /**
     * @var list<ListenerProviderInterface>
     */
    private $providers = [];

    /**
     * @return static
     */
    public function attachProvider(ListenerProviderInterface $provider): Dispatcher
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * @param object $event
     * @return object
     */
    public function dispatch(object $event): object
    {
        if (!$event instanceof ServiceEvent) {
            return $event;
        }

        foreach ($this->providers as $provider) {
            if (!$this->processListeners($event, $provider->getListenersForEvent($event))) {
                break;
            }
        }

        return $event;
    }

    /**
     * @param ServiceEvent $event
     * @param iterable $listeners
     * @return bool
     */
    private function processListeners(ServiceEvent $event, iterable $listeners): bool
    {
        foreach ($listeners as $listener) {
            if ($event->isPropagationStopped()) {
                return false;
            }

            assert(is_callable($listener));
            $listener($event);
        }

        return !$event->isPropagationStopped();
    }
}
