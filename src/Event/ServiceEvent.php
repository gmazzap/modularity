<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Event;

use Psr\EventDispatcher\StoppableEventInterface;

interface ServiceEvent extends StoppableEventInterface
{
    public const AFTER_EXTEND = 'after-extend';
    public const AFTER_OVERRIDE = 'after-override';
    public const AFTER_OVERRIDE_WITH_FACTORY = 'after-override-with-factory';
    public const AFTER_REGISTER = 'after-register';
    public const AFTER_REGISTER_FACTORY = 'after-register-factory';
    public const AFTER_RESOLVED = 'after-resolved';
    public const BEFORE_EXTEND = 'before-extend';
    public const BEFORE_OVERRIDE = 'before-override';
    public const BEFORE_OVERRIDE_WITH_FACTORY = 'before-override-with-factory';
    public const BEFORE_REGISTER = 'before-register';
    public const BEFORE_REGISTER_FACTORY = 'before-register-factory';
    public const BEFORE_RESOLVED = 'before-resolved';

    /**
     * @return string
     */
    public function type(): string;

    /**
     * @return string
     */
    public function serviceId(): string;
}
