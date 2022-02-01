<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Event;

use Psr\Container\ContainerInterface;

class AfterServiceResolved implements ServiceEvent
{
    use StoppableTrait;

    /**
     * @var string
     */
    protected $serviceId;

    /**
     * @var mixed
     */
    protected $service;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var bool
     */
    private $isExternalContainer;

    /**
     * @var bool
     */
    private $isFactory;

    /**
     * @param string $serviceId
     * @param mixed $service
     * @param ContainerInterface $container
     * @param bool $isExternalContainer
     * @param bool $isFactory
     * @return AfterServiceResolved
     */
    public static function new(
        string $serviceId,
        $service,
        ContainerInterface $container,
        bool $isExternalContainer,
        bool $isFactory
    ): AfterServiceResolved {

        return new self($serviceId, $service, $container, $isExternalContainer, $isFactory);
    }

    /**
     * @param string $serviceId
     * @param mixed $service
     * @param ContainerInterface $container
     * @param bool $isExternalContainer
     * @param bool $isFactory
     */
    protected function __construct(
        string $serviceId,
        $service,
        ContainerInterface $container,
        bool $isExternalContainer,
        bool $isFactory
    ) {

        $this->serviceId = $serviceId;
        $this->service = $service;
        $this->container = $container;
        $this->isExternalContainer = $isExternalContainer;
        $this->isFactory = $isFactory;
    }

    /**
     * @return string
     */
    public function type(): string
    {
        return self::AFTER_RESOLVED;
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

    /**
     * @return bool
     */
    public function isExternalContainer(): bool
    {
        return $this->isExternalContainer;
    }

    /**
     * @return bool
     */
    public function isFactory(): bool
    {
        return $this->isFactory;
    }
}
