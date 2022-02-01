<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Event;

use Inpsyde\Modularity\Module\ListeningModule;
use Psr\EventDispatcher\ListenerProviderInterface;

class ListeningModuleProvider implements ListenerProviderInterface
{
    /**
     * @var list<callable(ServiceEvent)>
     */
    private $listeners = [];

    /**
     * @param ListeningModule $module
     * @return void
     */
    public function addModule(ListeningModule $module): void
    {
        $this->listeners[] = [$module, 'listen'];
    }

    /**
     * @param object $event
     * @return list<callable(ServiceEvent)>
     */
    public function getListenersForEvent(object $event): iterable
    {
        if (!($event instanceof ServiceEvent)) {
            return [];
        }

        return $this->listeners;
    }
}
