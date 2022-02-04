<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Event;

trait StoppableTrait
{
    /**
     * @var bool
     */
    protected $stopped = false;

    /**
     * @return void
     */
    public function stop(): void
    {
        $this->stopped = true;
    }

    /**
     * @return bool
     */
    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}
