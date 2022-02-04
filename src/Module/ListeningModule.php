<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Module;

interface ListeningModule extends Module
{
    /**
     * @return iterable<callable>
     */
    public function listeners(): iterable;
}
