<?php

declare(strict_types=1);

namespace Inpsyde\Modularity;

use Inpsyde\Modularity\Container\ContainerConfigurator;
use Inpsyde\Modularity\Container\PackageProxyContainer;
use Inpsyde\Modularity\Event;
use Inpsyde\Modularity\Module\ExtendingModule;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\FactoryModule;
use Inpsyde\Modularity\Module\ListeningModule;
use Inpsyde\Modularity\Module\Module;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Properties\Properties;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

class Package
{
    /**
     * All the hooks fired in this class use this prefix.
     * @var string
     */
    private const HOOK_PREFIX = 'inpsyde.modularity.';

    /**
     * Identifier to access Properties in Container.
     *
     * @example
     * <code>
     * $package = Package::new();
     * $package->boot();
     *
     * $container = $package->container();
     * $container->has(Package::PROPERTIES);
     * $container->get(Package::PROPERTIES);
     * </code>
     *
     * @var string
     */
    public const PROPERTIES = 'properties';

    /**
     * Custom action to be used to add Modules to the package.
     * It might also be used to access package properties.
     *
     * @example
     * <code>
     * $package = Package::new();
     *
     * add_action(
     *      $package->hookName(Package::ACTION_INIT),
     *      $callback
     * );
     * </code>
     */
    public const ACTION_INIT = 'init';

    /**
     * Custom action which is triggered after the application
     * is booted to access container and properties.
     *
     * @example
     * <code>
     * $package = Package::new();
     *
     * add_action(
     *      $package->hookName(Package::ACTION_READY),
     *      $callback
     * );
     * </code>
     */
    public const ACTION_READY = 'ready';

    /**
     * Custom action which is triggered when application failed to boot.
     *
     * @example
     * <code>
     * $package = Package::new();
     *
     * add_action(
     *      $package->hookName(Package::ACTION_FAILED_BOOT),
     *      $callback
     * );
     * </code>
     */
    public const ACTION_FAILED_BOOT = 'failed-boot';

    /**
     * Custom action which is triggered when a package is connected.
     */
    public const ACTION_PACKAGE_CONNECTED = 'package-connected';

    /**
     * Custom action which is triggered when a package cannot be connected.
     */
    public const ACTION_FAILED_CONNECTION = 'failed-connection';

    /**
     * Custom action which is triggered when a service is not registered because of listeners.
     */
    public const ACTION_SERVICE_NOT_REGISTERED = 'service-not-registered';

    /**
     * Module states can be used to get information about your module.
     *
     * @example
     * <code>
     * $package = Package::new();
     * $package->moduleIs(SomeModule::class, Package::MODULE_ADDED); // false
     * $package->boot(new SomeModule());
     * $package->moduleIs(SomeModule::class, Package::MODULE_ADDED); // true
     * </code>
     */
    public const MODULE_ADDED = 'added';
    public const MODULE_NOT_ADDED = 'not-added';
    public const MODULE_REGISTERED = 'registered';
    public const MODULE_REGISTERED_FACTORIES = 'registered-factories';
    public const MODULE_EXTENDED = 'extended';
    public const MODULE_EXECUTED = 'executed';
    public const MODULE_EXECUTION_FAILED = 'executed-failed';
    public const MODULES_ALL = '*';

    /**
     * Custom states for the class.
     *
     * @example
     * <code>
     * $package = Package::new();
     * $package->statusIs(Package::IDLE); // true
     * $package->boot();
     * $package->statusIs(Package::BOOTED); // true
     * </code>
     */
    public const STATUS_IDLE = 2;
    public const STATUS_INITIALIZED = 4;
    public const STATUS_BOOTED = 8;
    public const STATUS_FAILED = -8;

    /**
     * Current state of the application.
     *
     * @see Package::STATUS_*
     *
     * @var int
     */
    private $status = self::STATUS_IDLE;

    /**
     * Contains the progress of all modules.
     *
     * @see Package::moduleProgress()
     *
     * @var array<string, list<string>>
     */
    private $moduleStatus = [self::MODULES_ALL => []];

    /**
     * Hashmap of where keys are names of connected packages, and values are boolean, true
     * if connection was successful.
     *
     * @see Package::connect()
     *
     * @var array<string, bool>
     */
    private $connectedPackages = [];

    /**
     * @var list<ExecutableModule>
     */
    private $executables = [];

    /**
     * @var Properties
     */
    private $properties;

    /**
     * @var Event\Dispatcher
     */
    private $eventDispatcher;

    /**
     * @var Event\ListeningModuleProvider|null
     */
    private $listeningModuleProvider = null;

    /**
     * @var ContainerConfigurator
     */
    private $containerConfigurator;

    /**
     * @param Properties $properties
     * @param ContainerInterface[] $containers
     *
     * @return Package
     */
    public static function new(Properties $properties, ContainerInterface  ...$containers): Package
    {
        return new self($properties, ...$containers);
    }

    /**
     * @param Properties $properties
     * @param ContainerInterface[] $containers
     */
    private function __construct(Properties $properties, ContainerInterface ...$containers)
    {
        $this->properties = $properties;
        $this->eventDispatcher = new Event\Dispatcher();

        $this->containerConfigurator = new ContainerConfigurator($containers, $this->eventDispatcher);
        $this->containerConfigurator->addService(
            self::PROPERTIES,
            static function () use ($properties) {
                return $properties;
            }
        );
    }

    /**
     * @param Module $module
     *
     * @return static
     * @throws \Exception
     */
    public function addModule(Module $module): Package
    {
        $this->assertStatus(self::STATUS_IDLE, 'access Container');

        $this->maybeAttachListenerProvider($module);

        $registeredServices = $this->addModuleServices($module, self::MODULE_REGISTERED);
        $registeredFactories = $this->addModuleServices($module, self::MODULE_REGISTERED_FACTORIES);
        $extended = $this->addModuleServices($module, self::MODULE_EXTENDED);
        $isExecutable = $module instanceof ExecutableModule;

        // ExecutableModules are collected and executed on Package::boot()
        // when the Container is being compiled.
        if ($isExecutable) {
            /** @var ExecutableModule $module */
            $this->executables[] = $module;
        }

        $added = $registeredServices || $registeredFactories || $extended || $isExecutable;
        $status = $added ? self::MODULE_ADDED : self::MODULE_NOT_ADDED;
        $this->moduleProgress($module->id(), $status);

        return $this;
    }

    /**
     * @param Package $package
     * @return bool
     * @throws \Exception
     */
    public function connect(Package $package): bool
    {
        if (($package === $this)) {
            return false;
        }

        $packageName = $package->name();
        $errorData = ['package' => $packageName, 'status' => $this->status];

        // Don't connect, if already connected
        if (array_key_exists($packageName, $this->connectedPackages)) {
            do_action(
                $this->hookName(self::ACTION_FAILED_CONNECTION),
                $packageName,
                new \WP_Error('already_connected', 'already connected', $errorData)
            );

            return false;
        }

        // Don't connect, if already booted or boot failed
        if (in_array($this->status, [self::STATUS_BOOTED, self::STATUS_FAILED], true)) {
            $this->connectedPackages[$packageName] = false;
            do_action(
                $this->hookName(self::ACTION_FAILED_CONNECTION),
                $packageName,
                new \WP_Error('no_connect_status', 'no connect status', $errorData)
            );

            return false;
        }

        $this->connectedPackages[$packageName] = true;

        // We put connected package's properties in this package's container, so that in modules
        // "run" method we can access them if we need to.
        $this->containerConfigurator->addService(
            sprintf('%s.%s', $package->name(), self::PROPERTIES),
            static function () use ($package): Properties {
                return $package->properties();
            }
        );

        // If the other package is booted, we can obtain a container, otherwise
        // we build a proxy container
        $container = $package->statusIs(self::STATUS_BOOTED)
            ? $package->container()
            : new PackageProxyContainer($package);

        $this->containerConfigurator->addContainer($container);

        do_action(
            $this->hookName(self::ACTION_PACKAGE_CONNECTED),
            $packageName,
            $this->status,
            $container instanceof PackageProxyContainer
        );

        return true;
    }

    /**
     * @param Module ...$defaultModules
     *
     * @return bool
     *
     * @throws \Throwable
     */
    public function boot(Module ...$defaultModules): bool
    {
        try {
            // don't allow to boot the application multiple times.
            $this->assertStatus(self::STATUS_IDLE, 'execute boot');

            // Add default Modules to the Application.
            array_map([$this, 'addModule'], $defaultModules);

            do_action(
                $this->hookName(self::ACTION_INIT),
                $this
            );
            // we want to lock adding new Modules and Containers now
            // to process everything and be able to compile the container.
            $this->progress(self::STATUS_INITIALIZED);

            if (count($this->executables) > 0) {
                $this->doExecute();
            }

            do_action(
                $this->hookName(self::ACTION_READY),
                $this
            );
        } catch (\Throwable $throwable) {
            $this->progress(self::STATUS_FAILED);
            do_action($this->hookName(self::ACTION_FAILED_BOOT), $throwable);

            if ($this->properties->isDebug()) {
                throw $throwable;
            }

            return false;
        }

        $this->progress(self::STATUS_BOOTED);

        return true;
    }

    /**
     * @param Module $module
     * @param string $status
     * @return bool
     */
    private function addModuleServices(Module $module, string $status): bool
    {
        switch ($status) {
            case self::MODULE_REGISTERED:
                $services = $module instanceof ServiceModule ? $module->services() : null;
                $addCallback = [$this->containerConfigurator, 'addService'];
                $isFactory = false;
                $isExtension = false;
                break;
            case self::MODULE_REGISTERED_FACTORIES:
                $services = $module instanceof FactoryModule ? $module->factories() : null;
                $addCallback = [$this->containerConfigurator, 'addFactory'];
                $isFactory = true;
                $isExtension = false;
                break;
            case self::MODULE_EXTENDED:
                $services = $module instanceof ExtendingModule ? $module->extensions() : null;
                $addCallback = [$this->containerConfigurator, 'addExtension'];
                $isFactory = false;
                $isExtension = true;
                break;
            default:
                return false;
        }

        if (!$services) {
            return false;
        }

        $ids = [];
        array_walk(
            $services,
            function (callable $service, string $id) use (
                $addCallback,
                &$ids,
                $module,
                $isFactory,
                $isExtension
            ) {
                $moduleId = $module->id();
                $isOverride = $this->containerConfigurator->hasService($id);
                $eventArgs = [$id, $addCallback, $moduleId, $isFactory, $isExtension, $isOverride];

                $eventBefore = $this->factoryEvent(Event\BeforeServiceAdded::class, ...$eventArgs);
                $this->eventDispatcher->dispatch($eventBefore);
                if (!$eventBefore->isServiceEnabled()) {
                    do_action($this->hookName(self::ACTION_SERVICE_NOT_REGISTERED), $id, $moduleId);

                    return;
                }

                /** @var callable(string, callable) $addCallback */
                $addCallback($id, $service);
                /** @var list<string> $ids */
                $ids[] = $id;

                $afterEvent = $this->factoryEvent(Event\AfterServiceAdded::class, ...$eventArgs);
                $this->eventDispatcher->dispatch($afterEvent);
            }
        );
        /** @var list<string> $ids */
        $this->moduleProgress($module->id(), $status, $ids);

        return true;
    }

    /**
     * @return void
     *
     * @throws \Throwable
     */
    private function doExecute(): void
    {
        foreach ($this->executables as $executable) {
            $success = $executable->run($this->container());
            $this->moduleProgress(
                $executable->id(),
                $success
                    ? self::MODULE_EXECUTED
                    : self::MODULE_EXECUTION_FAILED
            );
        }
    }

    /**
     * @param string $moduleId
     * @param string $type
     * @param list<string>|null $serviceIds
     *
     * @return  void
     */
    private function moduleProgress(string $moduleId, string $type, ?array $serviceIds = null)
    {
        isset($this->moduleStatus[$type]) or $this->moduleStatus[$type] = [];
        $this->moduleStatus[$type][] = $moduleId;

        if (!$serviceIds || !$this->properties->isDebug()) {
            $this->moduleStatus[self::MODULES_ALL][] = "{$moduleId} {$type}";

            return;
        }

        $description = sprintf('%s %s (%s)', $moduleId, $type, implode(', ', $serviceIds));
        $this->moduleStatus[self::MODULES_ALL][] = $description;
    }

    /**
     * @return array<string, list<string>>
     */
    public function modulesStatus(): array
    {
        return $this->moduleStatus;
    }

    /**
     * @return array<string, bool>
     */
    public function connectedPackages(): array
    {
        return $this->connectedPackages;
    }

    /**
     * @param string $packageName
     * @return bool
     */
    public function isPackageConnected(string $packageName): bool
    {
        return $this->connectedPackages[$packageName] ?? false;
    }

    /**
     * @param string $moduleId
     * @param string $status
     *
     * @return bool
     */
    public function moduleIs(string $moduleId, string $status): bool
    {
        return in_array($moduleId, $this->moduleStatus[$status] ?? [], true);
    }

    /**
     * Return the filter name to be used to extend modules of the plugin.
     *
     * If the plugin is single file `my-plugin.php` in plugins folder the filter name will be:
     * `inpsyde.modularity.my-plugin`.
     *
     * If the plugin is in a sub-folder e.g. `my-plugin/index.php` the filter name will be:
     * `inpsyde.modularity.my-plugin` anyway, so the file name is not relevant.
     *
     * @param string $suffix
     *
     * @return string
     * @see Package::name()
     *
     */
    public function hookName(string $suffix = ''): string
    {
        $filter = self::HOOK_PREFIX . $this->properties->baseName();

        if ($suffix) {
            $filter .= '.' . $suffix;
        }

        return $filter;
    }

    /**
     * @return Properties
     */
    public function properties(): Properties
    {
        return $this->properties;
    }

    /**
     * @return ContainerInterface
     *
     * @throws \Exception
     */
    public function container(): ContainerInterface
    {
        $this->assertStatus(self::STATUS_INITIALIZED, 'access Container', '>=');

        return $this->containerConfigurator->createReadOnlyContainer();
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->properties->baseName();
    }

    /**
     * @param int $status
     */
    private function progress(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @param int $status
     *
     * @return bool
     */
    public function statusIs(int $status): bool
    {
        return $this->status === $status;
    }

    /**
     * @param int $status
     * @param string $action
     * @param string $operator
     *
     * @throws \Exception
     * @psalm-suppress ArgumentTypeCoercion
     */
    private function assertStatus(int $status, string $action, string $operator = '=='): void
    {
        if (!version_compare((string) $this->status, (string) $status, $operator)) {
            throw new \Exception(sprintf("Can't %s at this point of application.", $action));
        }
    }

    /**
     * @param Module $module
     * @return void
     */
    private function maybeAttachListenerProvider(Module $module): void
    {
        if ($module instanceof ListenerProviderInterface) {
            $this->eventDispatcher->attachProvider($module);
        }

        if ($module instanceof ListeningModule) {
            if (!$this->listeningModuleProvider) {
                $this->listeningModuleProvider = new Event\ListeningModuleProvider();
                $this->eventDispatcher->attachProvider($this->listeningModuleProvider);
            }
            $this->listeningModuleProvider->addModule($module);
        }
    }

    /**
     * @param class-string<Event\BeforeServiceAdded>|class-string<Event\AfterServiceAdded> $class
     * @param string $serviceId
     * @param callable $serviceFactory
     * @param string $moduleId
     * @param bool $isFactory
     * @param bool $isExtension
     * @param bool $isOverride
     * @return ($class is class-string<Event\BeforeServiceAdded>
     *  ? Event\BeforeServiceAdded
     *  : Event\AfterServiceAdded)
     */
    private function factoryEvent(
        string $class,
        string $serviceId,
        callable $serviceFactory,
        string $moduleId,
        bool $isFactory,
        bool $isExtension,
        bool $isOverride
    ): Event\ServiceEvent {

        switch (true) {
            case $isExtension:
                $type = ($class === Event\BeforeServiceAdded::class)
                    ? Event\ServiceEvent::BEFORE_EXTEND
                    : Event\ServiceEvent::AFTER_EXTEND;
                break;
            case ($isFactory && $isOverride):
                $type = ($class === Event\BeforeServiceAdded::class)
                    ? Event\ServiceEvent::BEFORE_OVERRIDE_WITH_FACTORY
                    : Event\ServiceEvent::AFTER_OVERRIDE_WITH_FACTORY;
                break;
            case $isFactory:
                $type = ($class === Event\BeforeServiceAdded::class)
                    ? Event\ServiceEvent::BEFORE_REGISTER_FACTORY
                    : Event\ServiceEvent::AFTER_REGISTER_FACTORY;
                break;
            case $isOverride:
                $type = ($class === Event\BeforeServiceAdded::class)
                    ? Event\ServiceEvent::BEFORE_OVERRIDE
                    : Event\ServiceEvent::AFTER_OVERRIDE;
                break;
            default:
                $type = ($class === Event\BeforeServiceAdded::class)
                    ? Event\ServiceEvent::BEFORE_REGISTER
                    : Event\ServiceEvent::AFTER_REGISTER;
                break;
        }

        return new $class($type, $serviceId, $serviceFactory, $moduleId, $this->properties);
    }
}
