<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Event;

use Inpsyde\Modularity\Properties\Properties;

class AfterServiceAdded implements ServiceEvent
{
    use StoppableTrait;

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
     * @return AfterServiceAdded
     */
    public static function newAfterRegister(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): AfterServiceAdded {

        return new self(self::AFTER_REGISTER, $serviceId, $moduleId, $properties);
    }

    /**
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     * @return AfterServiceAdded
     */
    public static function newAfterRegisterFactory(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): AfterServiceAdded {

        return new self(self::AFTER_REGISTER_FACTORY, $serviceId, $moduleId, $properties);
    }

    /**
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     * @return AfterServiceAdded
     */
    public static function newAfterOverride(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): AfterServiceAdded {

        return new self(self::AFTER_OVERRIDE, $serviceId, $moduleId, $properties);
    }

    /**
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     * @return AfterServiceAdded
     */
    public static function newAfterOverrideWithFactory(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): AfterServiceAdded {

        return new self(self::AFTER_OVERRIDE_WITH_FACTORY, $serviceId, $moduleId, $properties);
    }

    /**
     * @param string $serviceId
     * @param string $moduleId
     * @param Properties $properties
     * @return AfterServiceAdded
     */
    public static function newAfterExtend(
        string $serviceId,
        string $moduleId,
        Properties $properties
    ): AfterServiceAdded {

        return new self(self::AFTER_EXTEND, $serviceId, $moduleId, $properties);
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
