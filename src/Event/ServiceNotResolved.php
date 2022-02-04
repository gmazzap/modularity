<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Event;

use Psr\Container\ContainerInterface;

final class ServiceNotResolved implements ServiceEvent
{
    use StoppableTrait;

    /**
     * @var mixed
     */
    private $service = null;

    /**
     * @var bool
     */
    private $hasService = false;

    /**
     * @var \Throwable
     */
    private $error;

    /**
     * @var string
     */
    private $serviceId;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param \Throwable $error
     * @param string $serviceId
     * @param ContainerInterface $container
     */
    public function __construct(
        \Throwable $error,
        string $serviceId,
        ContainerInterface $container
    ) {

        $this->error = $error;
        $this->serviceId = $serviceId;
        $this->container = $container;
    }

    /**
     * @return string
     */
    public function type(): string
    {
        return self::NOT_RESOLVED;
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


    /**
     * @param mixed $service
     * @return void
     */
    public function recoverWithService($service): void
    {
        if ($service !== null) {
            $this->service = $service;
            $this->hasService = true;
        }
    }

    /**
     * @return bool
     */
    public function hasService(): bool
    {
        return $this->hasService;
    }

    /**
     * @return mixed
     */
    public function service()
    {
        return $this->service;
    }
}
