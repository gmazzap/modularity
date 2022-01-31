<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Container;

use Inpsyde\Modularity\Properties\Properties;

/**
 * @psalm-type service-id non-empty-string
 * @psalm-type module-id non-empty-string
 * @psalm-type resolved-service mixed
 * @psalm-type stop-listeners callable():void
 * @psalm-type before-register callable(service-id,module-id,Properties,stop-listeners):bool
 * @psalm-type after-register callable(service-id,module-id,Properties,stop-listeners):void
 * @psalm-type before-get callable(service-id,stop-listeners):bool
 * @psalm-type after-get callable(service-id,resolved-service,stop-listeners):void
 */
class ServiceListeners
{
    public const BEFORE_REGISTER = 'before-register';
    public const AFTER_REGISTER = 'after-register';
    public const BEFORE_EXTEND = 'before-extend';
    public const AFTER_EXTEND = 'after-extend';
    public const BEFORE_GET = 'before-get';
    public const AFTER_GET = 'after-get';

    /**
     * @var array<string, list<array{callable,list<string>}>>
     */
    private $listeners = [];

    /**
     * @param before-register $listener
     * @param string ...$targetServices
     * @return static
     */
    public function onBeforeRegister(
        callable $listener,
        string ...$targetServices
    ): ServiceListeners {

        return $this->addListener(self::BEFORE_REGISTER, $listener, $targetServices);
    }

    /**
     * @param after-register $listener
     * @param string ...$targetServices
     * @return static
     */
    public function onAfterRegister(
        callable $listener,
        string ...$targetServices
    ): ServiceListeners {

        return $this->addListener(self::AFTER_REGISTER, $listener, $targetServices);
    }

    /**
     * @param before-register $listener
     * @param string ...$targetServices
     * @return static
     */
    public function onBeforeExtend(callable $listener, string ...$targetServices): ServiceListeners
    {
        return $this->addListener(self::BEFORE_EXTEND, $listener, $targetServices);
    }

    /**
     * @param after-register $listener
     * @param string ...$targetServices
     * @return static
     */
    public function onAfterExtend(callable $listener, string ...$targetServices): ServiceListeners
    {
        return $this->addListener(self::AFTER_EXTEND, $listener, $targetServices);
    }

    /**
     * @param before-get $listener
     * @param string ...$targetServices
     * @return static
     */
    public function onBeforeGet(callable $listener, string ...$targetServices): ServiceListeners
    {
        return $this->addListener(self::BEFORE_GET, $listener, $targetServices);
    }

    /**
     * @param after-get $listener
     * @param string ...$targetServices
     * @return static
     */
    public function onAfterGet(callable $listener, string ...$targetServices): ServiceListeners
    {
        return $this->addListener(self::AFTER_GET, $listener, $targetServices);
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
     * @param string $type
     * @param callable $listener
     * @param array<string> $target
     * @return static
     */
    private function addListener(string $type, callable $listener, array $target): ServiceListeners
    {
        isset($this->listeners[$type]) or $this->listeners[$type] = [];
        $this->listeners[$type][] = [$listener, array_values($target)];

        return $this;
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
