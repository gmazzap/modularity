<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Event;

use Psr\Container\ContainerInterface;

final class BeforeServiceResolved implements ServiceEvent
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
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param string $type
     * @param string $serviceId
     * @param ContainerInterface $container
     */
    public function __construct(
        string $type,
        string $serviceId,
        ContainerInterface $container
    ) {

        $this->type = $type;
        $this->serviceId = $serviceId;
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
     * @return ContainerInterface
     */
    public function container(): ContainerInterface
    {
        return $this->container;
    }
}
