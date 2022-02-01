<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Event;

use Inpsyde\Modularity\Properties\Properties;

class BeforeServiceAdded implements ServiceEvent
{
    use StoppableTrait;

    /**
     * @var bool
     */
    protected $serviceAllowed = true;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $serviceId;

    /**
     * @var string
     */
    protected $moduleId;

    /**
     * @var Properties
     */
    protected $properties;

    /**
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     * @return BeforeServiceAdded
     */
    public static function newBeforeRegister(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): BeforeServiceAdded {

        return new self(self::BEFORE_REGISTER, $serviceId, $moduleId, $properties);
    }

    /**
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     * @return BeforeServiceAdded
     */
    public static function newBeforeRegisterFactory(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): BeforeServiceAdded {

        return new self(self::BEFORE_REGISTER_FACTORY, $serviceId, $moduleId, $properties);
    }

    /**
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     * @return BeforeServiceAdded
     */
    public static function newBeforeOverride(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): BeforeServiceAdded {

        return new self(self::BEFORE_OVERRIDE, $serviceId, $moduleId, $properties);
    }

    /**
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     * @return BeforeServiceAdded
     */
    public static function newBeforeOverrideWithFactory(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): BeforeServiceAdded {

        return new self(self::BEFORE_OVERRIDE_WITH_FACTORY, $serviceId, $moduleId, $properties);
    }

    /**
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     * @return BeforeServiceAdded
     */
    public static function newBeforeExtend(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): BeforeServiceAdded {

        return new self(self::BEFORE_EXTEND, $serviceId, $moduleId, $properties);
    }

    /**
     * @param string $name
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     */
    protected function __construct(
        string $name,
        string $serviceId,
        string $moduleId,
        Properties $properties
    ) {
        $this->name = $name;
        $this->serviceId = $serviceId;
        $this->moduleId = $moduleId;
        $this->properties = $properties;
    }

    /**
     * @return void
     */
    public function disableService(): void
    {
        $this->serviceAllowed = false;
    }

    /**
     * @return void
     */
    public function enableService(): void
    {
        $this->serviceAllowed = true;
    }

    /**
     * @return bool
     */
    public function isServiceEnabled(): bool
    {
        return $this->serviceAllowed;
    }

    /**
     * @return string
     */
    public function type(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function serviceId(): string
    {
        return $this->serviceId;
    }

    /**
     * @return string
     */
    public function moduleId(): string
    {
        return $this->moduleId;
    }

    /**
     * @return Properties
     */
    public function properties(): Properties
    {
        return $this->properties;
    }
}
