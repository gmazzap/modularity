<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Event;

use Psr\Container\ContainerInterface;

class BeforeServiceResolved implements ServiceEvent
{
    use StoppableTrait;

    /**
     * @var string
     */
    protected $serviceId;

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
     * @param ContainerInterface $container
     * @param bool $isExternalContainer
     * @param bool $isFactory
     * @return BeforeServiceResolved
     */
    public static function new(
        string $serviceId,
        ContainerInterface $container,
        bool $isExternalContainer,
        bool $isFactory
    ): BeforeServiceResolved {

        return new self($serviceId, $container, $isExternalContainer, $isFactory);
    }

    /**
     * @param string $serviceId
     * @param ContainerInterface $container
     * @param bool $isExternalContainer
     * @param bool $isFactory
     */
    protected function __construct(
        string $serviceId,
        ContainerInterface $container,
        bool $isExternalContainer,
        bool $isFactory
    ) {

        $this->serviceId = $serviceId;
        $this->container = $container;
        $this->isExternalContainer = $isExternalContainer;
        $this->isFactory = $isFactory;
    }

    /**
     * @return string
     */
    public function type(): string
    {
        return self::BEFORE_RESOLVED;
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
