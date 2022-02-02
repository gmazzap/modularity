<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Event;

use Inpsyde\Modularity\Properties\Properties;

final class AfterServiceAdded implements ServiceEvent
{
    use StoppableTrait;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    private $serviceId;

    /**
     * @var callable
     */
    private $serviceFactory;

    /**
     * @var string
     */
    private $moduleId;

    /**
     * @var Properties
     */
    private $properties;

    /**
     * @param string $type
     * @param string $serviceId
     * @param callable $serviceFactory
     * @param string $moduleId
     * @param Properties $properties
     */
    public function __construct(
        string $type,
        string $serviceId,
        callable $serviceFactory,
        string $moduleId,
        Properties $properties
    ) {
        $this->type = $type;
        $this->serviceId = $serviceId;
        $this->serviceFactory = $serviceFactory;
        $this->moduleId = $moduleId;
        $this->properties = $properties;
    }

    /**
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function serviceId(): string
    {
        return $this->serviceId;
    }

    /**
     * @return callable
     */
    public function serviceFactory(): callable
    {
        return $this->serviceFactory;
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
