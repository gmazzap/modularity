<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Container;

use Inpsyde\Modularity\Properties\Properties;

class ServiceListeners
{
    public const BEFORE_REGISTER = 'before-register';
    public const AFTER_REGISTER = 'after-register';
    public const BEFORE_EXTEND = 'before-extend';
    public const AFTER_EXTEND = 'after-extend';
    public const BEFORE_GET = 'before-get';
    public const AFTER_GET = 'after-get';

    private const EVENTS = [
        self::BEFORE_REGISTER,
        self::AFTER_REGISTER,
        self::BEFORE_EXTEND,
        self::AFTER_EXTEND,
        self::BEFORE_GET,
        self::AFTER_GET,
    ];

    /**
     * @var array<string, list<array{callable,list<string>}>>
     */
    private $listeners = [];

    /**
     * @param ServiceListeners::BEFORE_*|ServiceListeners::AFTER_* $type
     * @param callable $listener
     * @param array<string> $target
     * @return static
     */
    public function attach(
        string $event,
        callable $listener,
        string...$targetServices
    ): ServiceListeners {

        if (!in_array($event, self::EVENTS, true)) {
            throw new \Exception("{$event} is not a valid service listener event.");
        }

        isset($this->listeners[$event]) or $this->listeners[$event] = [];
        $this->listeners[$event][] = [$listener, array_values($targetServices)];

        return $this;
    }

    /**
     * @param string $serviceId
     * @return void
     */
    public function updateBeforeGet(string $serviceId): void
    {
        $this->update($serviceId, self::BEFORE_GET);
    }

    /**
     * @param string $serviceId
     * @param mixed $service
     * @return void
     */
    public function updateAfterGet(string $serviceId, $service): void
    {
        $this->update($serviceId, self::AFTER_GET, $service);
    }

    /**
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     * @return bool
     */
    public function updateBeforeRegister(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): bool {

        return $this->update($serviceId, self::BEFORE_REGISTER, $moduleId, $properties);
    }

    /**
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     * @return void
     */
    public function updateAfterRegister(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): void {

        $this->update($serviceId, self::AFTER_REGISTER, $moduleId, $properties);
    }

    /**
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     * @return bool
     */
    public function updateBeforeExtend(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): bool {

        return $this->update($serviceId, self::BEFORE_EXTEND, $moduleId, $properties);
    }

    /**
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     * @return void
     */
    public function updateAfterExtend(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): void {

        $this->update($serviceId, self::AFTER_EXTEND, $moduleId, $properties);
    }

    /**
     * @param string $serviceId
     * @param string $event
     * @param list<mixed> $args
     * @return bool
     */
    private function update(string $serviceId, string $event, ...$args): bool
    {
        if (empty($this->listeners[$event])) {
            return true;
        }

        $returnValue = true;
        $stopped = false;
        $args[] = static function () use (&$stopped): void {
            $stopped = true;
        };
        foreach ($this->listeners[$event] as [$listener, $targetServices]) {
            if ($stopped) {
                return $returnValue;
            }

            if (!$targetServices || in_array($serviceId, $targetServices, true)) {
                $returnValue = ($listener($serviceId, ...$args) !== false) && $returnValue;
            }
        }

        return $returnValue;
    }
}
