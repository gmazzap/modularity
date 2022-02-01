<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Module;

use Inpsyde\Modularity\Event\ServiceEvent;

interface ListeningModule extends Module
{
    public function listen(ServiceEvent $event): void;
}
