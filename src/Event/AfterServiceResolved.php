<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Event;

use Psr\Container\ContainerInterface;

final class AfterServiceResolved implements ServiceEvent
{
    use StoppableTrait;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $serviceId;

    /**
     * @var mixed
     */
    private $service;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param string $type
     * @param string $serviceId
     * @param mixed $service
     * @param ContainerInterface $container
     */
    public function __construct(
        string $type,
        string $serviceId,
        $service,
        ContainerInterface $container
    ) {

        $this->type = $type;
        $this->serviceId = $serviceId;
        $this->service = $service;
        $this->container = $container;
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
     * @return mixed
     */
    public function service()
    {
        return $this->service;
    }

    /**
     * @return ContainerInterface
     */
    public function container(): ContainerInterface
    {
        return $this->container;
    }
}
