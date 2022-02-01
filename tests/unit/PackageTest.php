<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit;

use Brain\Monkey;
use Inpsyde\Modularity\Event\AfterServiceAdded;
use Inpsyde\Modularity\Event\AfterServiceResolved;
use Inpsyde\Modularity\Event\BeforeServiceAdded;
use Inpsyde\Modularity\Event\BeforeServiceResolved;
use Inpsyde\Modularity\Event\ServiceEvent;
use Inpsyde\Modularity\Module\ExtendingModule;
use Inpsyde\Modularity\Module\FactoryModule;
use Inpsyde\Modularity\Module\ListeningModule;
use Inpsyde\Modularity\Module\Module;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Package;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Properties\Properties;
use Inpsyde\Modularity\Tests\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

class PackageTest extends TestCase
{
    /**
     * @test
     */
    public function testBasic(): void
    {
        $expectedName = 'foo';
        $propertiesStub = $this->mockProperties($expectedName);

        $package = Package::new($propertiesStub);

        static::assertTrue($package->statusIs(Package::STATUS_IDLE));
        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_BOOTED));
        static::assertSame($expectedName, $package->name());
        static::assertInstanceOf(Properties::class, $package->properties());
        static::assertInstanceOf(ContainerInterface::class, $package->container());
        static::assertEmpty($package->modulesStatus()[Package::MODULES_ALL]);
    }

    /**
     * @param string $suffix
     * @param string $baseName
     * @param string $expectedHookName
     *
     * @test
     * @dataProvider provideHookNameSuffix
     */
    public function testHookName(string $suffix, string $baseName, string $expectedHookName): void
    {
        $propertiesStub = $this->mockProperties($baseName);
        $package = Package::new($propertiesStub);
        static::assertSame($expectedHookName, $package->hookName($suffix));
    }

    /**
     * @return \Generator
     */
    public function provideHookNameSuffix(): \Generator
    {
        $expectedName = 'baseName';
        $baseHookName = 'inpsyde.modularity.' . $expectedName;
        yield 'no suffix' => [
            '',
            $expectedName,
            $baseHookName,
        ];

        yield 'failed boot' => [
            Package::ACTION_FAILED_BOOT,
            $expectedName,
            $baseHookName . '.' . Package::ACTION_FAILED_BOOT,
        ];

        yield 'init' => [
            Package::ACTION_INIT,
            $expectedName,
            $baseHookName . '.' . Package::ACTION_INIT,
        ];

        yield 'ready' => [
            Package::ACTION_READY,
            $expectedName,
            $baseHookName . '.' . Package::ACTION_READY,
        ];
    }

    /**
     * @test
     */
    public function testBootWithEmptyModule(): void
    {
        $expectedId = 'my-module';

        $moduleStub = $this->mockModule($expectedId);
        $propertiesStub = $this->mockProperties('name', false);

        $package = Package::new($propertiesStub);

        static::assertTrue($package->boot($moduleStub));
        static::assertTrue($package->moduleIs($expectedId, Package::MODULE_NOT_ADDED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_EXTENDED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_ADDED));

        // booting again will do nothing.
        static::assertFalse($package->boot());
    }

    /**
     * @test
     */
    public function testBootWithServiceModule(): void
    {
        $moduleId = 'my-service-module';
        $serviceId = 'service-id';

        $module = $this->mockModule($moduleId, ServiceModule::class);
        $module->shouldReceive('services')->andReturn($this->stubServices($serviceId));

        $package = Package::new($this->mockProperties());

        static::assertTrue($package->boot($module));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->container()->has($serviceId));
    }

    /**
     * @test
     */
    public function testBootWithFactoryModule(): void
    {
        $moduleId = 'my-factory-module';
        $factoryId = 'factory-id';

        $module = $this->mockModule($moduleId, FactoryModule::class);
        $module->shouldReceive('factories')->andReturn($this->stubServices($factoryId));

        $package = Package::new($this->mockProperties());

        static::assertTrue($package->boot($module));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->container()->has($factoryId));
    }

    /**
     * @test
     */
    public function testBootWithExtendingModuleWithNonExistingService(): void
    {
        $moduleId = 'my-extension-module';
        $extensionId = 'extension-id';

        $module = $this->mockModule($moduleId, ExtendingModule::class);
        $module->shouldReceive('extensions')->andReturn($this->stubServices($extensionId));

        $package = Package::new($this->mockProperties());

        static::assertTrue($package->boot($module));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        // false because extending a service not in container
        static::assertFalse($package->container()->has($extensionId));
    }

    /**
     * @test
     */
    public function testBootWithExtendingModuleWithExistingService(): void
    {
        $moduleId = 'my-extension-module';
        $serviceId = 'service-id';

        $module = $this->mockModule($moduleId, ServiceModule::class, ExtendingModule::class);
        $module->shouldReceive('services')->andReturn($this->stubServices($serviceId));
        $module->shouldReceive('extensions')->andReturn($this->stubServices($serviceId));

        $package = Package::new($this->mockProperties());

        static::assertTrue($package->boot($module));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->container()->has($serviceId));
    }

    /**
     * Test if on Properties::isDebug() === false no Exception is thrown
     * and Boostrap::boot() returns false.
     *
     * @test
     */
    public function testBootWithThrowingModuleAndDebugFalse(): void
    {
        $exception = new \Exception("Catch me if you can!");

        $module = $this->mockModule('id', ExecutableModule::class);
        $module->shouldReceive('run')->andThrow($exception);

        $package = Package::new($this->mockProperties('basename', false));

        $failedHook = $package->hookName(Package::ACTION_FAILED_BOOT);
        Monkey\Actions\expectDone($failedHook)->once()->with($exception);

        static::assertFalse($package->boot($module));
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
    }

    /**
     * Test if on Properties::isDebug() === true an Exception is thrown.
     *
     * @test
     */
    public function testBootWithThrowingModuleAndDebugTrue(): void
    {
        $exception = new \Exception("Catch me if you can!");

        $module = $this->mockModule('id', ExecutableModule::class);
        $module->shouldReceive('run')->andThrow($exception);

        $package = Package::new($this->mockProperties('basename', true));

        $failedHook = $package->hookName(Package::ACTION_FAILED_BOOT);
        Monkey\Actions\expectDone($failedHook)->once()->with($exception);

        $this->expectExceptionObject($exception);
        $package->boot($module);
    }

    /**
     * @test
     */
    public function testBootWithExecutableModule(): void
    {
        $moduleId = 'executable-module';
        $module = $this->mockModule($moduleId, ExecutableModule::class);
        $module->shouldReceive('run')->andReturn(true);

        $package = Package::new($this->mockProperties());

        static::assertTrue($package->boot($module));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXECUTED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXECUTION_FAILED));
    }

    /**
     * Test, when the ExecutableModule::run() return false, that the state is correctly set.
     *
     * @test
     */
    public function testBootWithExecutableModuleFailed(): void
    {
        $moduleId = 'executable-module';
        $module = $this->mockModule($moduleId, ExecutableModule::class);
        $module->shouldReceive('run')->andReturn(false);

        $package = Package::new($this->mockProperties());

        static::assertTrue($package->boot($module));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXECUTED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXECUTION_FAILED));
    }

    /**
     * Test, when multiple modules are added, and debug is true, 'modules' status is set correctly.
     *
     * @test
     */
    public function testStatusForMultipleModulesWhenDebug(): void
    {
        $emptyModule = $this->mockModule('empty');
        $emptyServicesModule = $this->mockModule('empty_services', ServiceModule::class);
        $emptyFactoriesModule = $this->mockModule('empty_factories', FactoryModule::class);
        $emptyExtensionsModule = $this->mockModule('empty_extensions', ExtendingModule::class);

        $servicesModule = $this->mockModule('service', ServiceModule::class);
        $servicesModule->shouldReceive('services')->andReturn($this->stubServices('S1', 'S2'));

        $factoriesModule = $this->mockModule('factory', FactoryModule::class);
        $factoriesModule->shouldReceive('factories')->andReturn($this->stubServices('F'));

        $extendingModule = $this->mockModule('extension', ExtendingModule::class);
        $extendingModule->shouldReceive('extensions')->andReturn($this->stubServices('E'));

        $multiModule = $this->mockModule(
            'multi',
            ServiceModule::class,
            ExtendingModule::class,
            FactoryModule::class
        );
        $multiModule->shouldReceive('services')->andReturn($this->stubServices('MS1'));
        $multiModule->shouldReceive('factories')->andReturn($this->stubServices('MF1', 'MF2'));
        $multiModule->shouldReceive('extensions')->andReturn($this->stubServices('ME1', 'ME2'));

        $package = Package::new($this->mockProperties('name', true))
            ->addModule($emptyServicesModule)
            ->addModule($emptyFactoriesModule)
            ->addModule($emptyExtensionsModule)
            ->addModule($servicesModule)
            ->addModule($multiModule)
            ->addModule($factoriesModule);

        static::assertTrue($package->boot($emptyModule, $extendingModule));

        $expectedStatus = [
            Package::MODULES_ALL => [
                'empty_services ' . Package::MODULE_NOT_ADDED,
                'empty_factories ' . Package::MODULE_NOT_ADDED,
                'empty_extensions ' . Package::MODULE_NOT_ADDED,
                'service ' . Package::MODULE_REGISTERED . ' (S1, S2)',
                'service ' . Package::MODULE_ADDED,
                'multi ' . Package::MODULE_REGISTERED . ' (MS1)',
                'multi ' . Package::MODULE_REGISTERED_FACTORIES . ' (MF1, MF2)',
                'multi ' . Package::MODULE_EXTENDED . ' (ME1, ME2)',
                'multi ' . Package::MODULE_ADDED,
                'factory ' . Package::MODULE_REGISTERED_FACTORIES . ' (F)',
                'factory ' . Package::MODULE_ADDED,
                'empty ' . Package::MODULE_NOT_ADDED,
                'extension ' . Package::MODULE_EXTENDED . ' (E)',
                'extension ' . Package::MODULE_ADDED,
            ],
            Package::MODULE_NOT_ADDED => [
                'empty_services',
                'empty_factories',
                'empty_extensions',
                'empty',
            ],
            Package::MODULE_REGISTERED => [
                'service',
                'multi',
            ],
            Package::MODULE_REGISTERED_FACTORIES => [
                'multi',
                'factory',
            ],
            Package::MODULE_EXTENDED => [
                'multi',
                'extension',
            ],
            Package::MODULE_ADDED => [
                'service',
                'multi',
                'factory',
                'extension',
            ],
        ];

        $actualStatus = $package->modulesStatus();

        ksort($expectedStatus, SORT_STRING);
        ksort($actualStatus, SORT_STRING);

        static::assertSame($expectedStatus, $actualStatus);
    }

    /**
     * Test, when multiple modules are added, and debug is false, 'modules' status is set correctly.
     *
     * @test
     */
    public function testStatusForMultipleModulesWhenNotDebug(): void
    {
        $emptyModule = $this->mockModule('empty');
        $emptyServicesModule = $this->mockModule('empty_services', ServiceModule::class);
        $emptyFactoriesModule = $this->mockModule('empty_factories', FactoryModule::class);
        $emptyExtensionsModule = $this->mockModule('empty_extensions', ExtendingModule::class);

        $servicesModule = $this->mockModule('service', ServiceModule::class);
        $servicesModule->shouldReceive('services')->andReturn($this->stubServices('S1', 'S2'));

        $factoriesModule = $this->mockModule('factory', FactoryModule::class);
        $factoriesModule->shouldReceive('factories')->andReturn($this->stubServices('F'));

        $extendingModule = $this->mockModule('extension', ExtendingModule::class);
        $extendingModule->shouldReceive('extensions')->andReturn($this->stubServices('E'));

        $multiModule = $this->mockModule(
            'multi',
            ServiceModule::class,
            ExtendingModule::class,
            FactoryModule::class
        );
        $multiModule->shouldReceive('services')->andReturn($this->stubServices('MS1'));
        $multiModule->shouldReceive('factories')->andReturn($this->stubServices('MF1', 'MF2'));
        $multiModule->shouldReceive('extensions')->andReturn($this->stubServices('ME1', 'ME2'));

        $package = Package::new($this->mockProperties('name', false))
            ->addModule($emptyServicesModule)
            ->addModule($emptyFactoriesModule)
            ->addModule($emptyExtensionsModule)
            ->addModule($servicesModule)
            ->addModule($multiModule)
            ->addModule($factoriesModule);

        static::assertTrue($package->boot($emptyModule, $extendingModule));

        $expectedStatus = [
            Package::MODULES_ALL => [
                'empty_services ' . Package::MODULE_NOT_ADDED,
                'empty_factories ' . Package::MODULE_NOT_ADDED,
                'empty_extensions ' . Package::MODULE_NOT_ADDED,
                'service ' . Package::MODULE_REGISTERED,
                'service ' . Package::MODULE_ADDED,
                'multi ' . Package::MODULE_REGISTERED,
                'multi ' . Package::MODULE_REGISTERED_FACTORIES,
                'multi ' . Package::MODULE_EXTENDED,
                'multi ' . Package::MODULE_ADDED,
                'factory ' . Package::MODULE_REGISTERED_FACTORIES,
                'factory ' . Package::MODULE_ADDED,
                'empty ' . Package::MODULE_NOT_ADDED,
                'extension ' . Package::MODULE_EXTENDED,
                'extension ' . Package::MODULE_ADDED,
            ],
            Package::MODULE_NOT_ADDED => [
                'empty_services',
                'empty_factories',
                'empty_extensions',
                'empty',
            ],
            Package::MODULE_REGISTERED => [
                'service',
                'multi',
            ],
            Package::MODULE_REGISTERED_FACTORIES => [
                'multi',
                'factory',
            ],
            Package::MODULE_EXTENDED => [
                'multi',
                'extension',
            ],
            Package::MODULE_ADDED => [
                'service',
                'multi',
                'factory',
                'extension',
            ],
        ];

        $actualStatus = $package->modulesStatus();

        ksort($expectedStatus, SORT_STRING);
        ksort($actualStatus, SORT_STRING);

        static::assertSame($expectedStatus, $actualStatus);
    }

    /**
     * Test we can connect services across packages.
     *
     * @test
     */
    public function testPackageConnection(): void
    {
        $module1 = $this->mockModule('module_1', ServiceModule::class);
        $module1->shouldReceive('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->mockProperties('package_1', false))
            ->addModule($module1);

        $module2 = $this->mockModule('module_2', ServiceModule::class);
        $module2->shouldReceive('services')->andReturn($this->stubServices('service_2'));
        $package2 = Package::new($this->mockProperties('package_2', false))
            ->addModule($module2);

        $package1->boot();

        $connected = $package2->connect($package1);
        $package2->boot();

        static::assertTrue($connected);
        static::assertSame(['package_1' => true], $package2->connectedPackages());
        // retrieve a Package 1's service from Package 2's container.
        static::assertInstanceOf(\ArrayObject::class, $package2->container()->get('service_1'));
    }

    /**
     * Test we can not connect services when the package how call connect is booted.
     *
     * @test
     */
    public function testPackageConnectionFailsIfBooted(): void
    {
        $module1 = $this->mockModule('module_1', ServiceModule::class);
        $module1->shouldReceive('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->mockProperties('package_1', false))
            ->addModule($module1);

        $module2 = $this->mockModule('module_2', ServiceModule::class);
        $module2->shouldReceive('services')->andReturn($this->stubServices('service_2'));
        $package2 = Package::new($this->mockProperties('package_2', false))
            ->addModule($module2);

        $package1->boot();
        $package2->boot();

        $connected = $package2->connect($package1);

        static::assertFalse($connected);
        static::assertSame(['package_1' => false], $package2->connectedPackages());
    }

    /**
     * Test we can connect services even if target package is not booted yet.
     *
     * @test
     */
    public function testPackageConnectionWithProxyContainer(): void
    {
        $module1 = $this->mockModule('module_1', ServiceModule::class);
        $module1->shouldReceive('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->mockProperties('package_1', false))
            ->addModule($module1);

        $module2 = $this->mockModule('module_2', ServiceModule::class);
        $module2->shouldReceive('services')->andReturn($this->stubServices('service_2'));
        $package2 = Package::new($this->mockProperties('package_2', false))
            ->addModule($module2);

        $connected = $package2->connect($package1);
        $package2->boot();

        static::assertTrue($connected);
        static::assertSame(['package_1' => true], $package2->connectedPackages());

        // We successfully connect before booting, but we need to boot to retrieve services
        $package1->boot();
        $container = $package2->container();

        // retrieve a Package 1's service from Package 2's container.
        $connectedService = $container->get('service_1');
        // retrieve the Package 1's properties from Package 2's container.
        $connectedProperties = $container->get('package_1.' . Package::PROPERTIES);

        static::assertInstanceOf(\ArrayObject::class, $connectedService);
        static::assertInstanceOf(Properties::class, $connectedProperties);
        static::assertSame('package_1', $connectedProperties->baseName());
    }

    /**
     * Test that connecting packages not booted, fails when accessing services
     *
     * @test
     */
    public function testPackageConnectionWithProxyContainerFailsIfNoBoot(): void
    {
        $module1 = $this->mockModule('module_1', ServiceModule::class);
        $module1->shouldReceive('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->mockProperties('package_1', false))
            ->addModule($module1);

        $module2 = $this->mockModule('module_2', ServiceModule::class);
        $module2->shouldReceive('services')->andReturn($this->stubServices('service_2'));
        $package2 = Package::new($this->mockProperties('package_2', false))
            ->addModule($module2);

        $connected = $package2->connect($package1);
        $package2->boot();

        static::assertTrue($connected);
        static::assertSame(['package_1' => true], $package2->connectedPackages());

        // we got a "not found" exception because `PackageProxyContainer::has()` return false,
        // because $package1 is not booted
        $this->expectExceptionMessageMatches('/not found/i');
        $package2->container()->get('service_1');
    }

    /**
     * Test we can connect packages once.
     *
     * @test
     */
    public function testPackageCanOnlyBeConnectedOnce(): void
    {
        $module1 = $this->mockModule('module_1', ServiceModule::class);
        $module1->shouldReceive('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->mockProperties('package_1', false))
            ->addModule($module1);

        $module2 = $this->mockModule('module_2', ServiceModule::class);
        $module2->shouldReceive('services')->andReturn($this->stubServices('service_2'));
        $package2 = Package::new($this->mockProperties('package_2', false))
            ->addModule($module2);

        $actionOk = $package2->hookName(Package::ACTION_PACKAGE_CONNECTED);
        $actionFailed = $package2->hookName(Package::ACTION_FAILED_CONNECTION);

        Monkey\Actions\expectDone($actionOk)->once();
        Monkey\Actions\expectDone($actionFailed)->once();

        $connected1 = $package2->connect($package1);
        $connected2 = $package2->connect($package1);

        static::assertTrue($connected1);
        static::assertFalse($connected2);
    }

    /**
     * Test we can not connect packages with themselves.
     *
     * @test
     */
    public function testPackageCanNotBeConnectedWithThemselves(): void
    {
        $module1 = $this->mockModule('module_1', ServiceModule::class);
        $module1->shouldReceive('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->mockProperties('package_1', false))
            ->addModule($module1);

        $action = $package1->hookName(Package::ACTION_FAILED_CONNECTION);
        Monkey\Actions\expectDone($action)->never();

        static::assertFalse($package1->connect($package1));
    }

    /**
     * Test we can use "before register" listeners to prevent services registration, and in that
     * case an action is fired with service and module IDs.
     *
     * @test
     */
    public function testBeforeRegisterListenersCanPreventServiceRegistration(): void
    {
        $module1 = $this->mockModule('module1', ServiceModule::class);
        $module1->shouldReceive('services')->andReturn($this->stubServices('a', 'b', 'c'));

        $module2 = $this->mockModule('module2', ServiceModule::class);
        $module2->shouldReceive('services')->andReturn($this->stubServices('d', 'e', 'f'));

        $listener = new class implements ListeningModule
        {
            use ModuleClassNameIdTrait;

            public function listen(ServiceEvent $event): void
            {
                if (
                    !($event instanceof BeforeServiceAdded)
                    || ($event->moduleId() !== 'module2')
                    || ($event->serviceId() === 'e')
                ) {
                    return;
                }

                $event->disableService();
                $event->stop();
            }
        };

        $package = Package::new($this->mockProperties('test-package', false))
            ->addModule($listener);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_SERVICE_NOT_REGISTERED))
            ->once()->with('d', 'module2')
            ->andAlsoExpectIt()
            ->once()->with('f', 'module2');

        $package->addModule($module1)->addModule($module2);

        $package->boot();
        $container = $package->container();

        static::assertTrue($container->has('a'));
        static::assertTrue($container->has('b'));
        static::assertTrue($container->has('c'));
        static::assertFalse($container->has('d'));
        static::assertTrue($container->has('e'));
        static::assertFalse($container->has('f'));
    }

    /**
     * Test "after register" listeners can be used to implement dependencies of modules on services.
     *
     * @test
     */
    public function testListenersCanBeUsedForModulesDependencies(): void
    {
        $module1 = $this->mockModule('module-1', ServiceModule::class);
        $module1->shouldReceive('services')->andReturn($this->stubServices('a', 'b'));

        $module2 = $this->mockModule('module-2', ServiceModule::class);
        $module2->shouldReceive('services')->andReturn($this->stubServices('c', 'd'));

        $module3 = $this->mockModule('module-3', ServiceModule::class);
        $module3->shouldReceive('services')->andReturn($this->stubServices('e', 'f'));

        $package = Package::new($this->mockProperties('test-package', false));

        $listener = new class($package, $module2, $module3) implements ListeningModule
        {
            use ModuleClassNameIdTrait;
            private $package;
            private $module2;
            private $module3;

            public function __construct(Package $package, $module2, $module3) {
                $this->package = $package;
                $this->module2 = $module2;
                $this->module3 = $module3;
            }

            public function listen(ServiceEvent $event): void
            {
                if (!($event instanceof AfterServiceAdded)) {
                    return;
                }

                switch ($event->serviceId()) {
                    case 'b':
                        $this->package->addModule($this->module2);
                        break;
                    case 'meh':
                        $this->package->addModule($this->module3);
                        break;
                }
            }
        };

        $package->addModule($listener)->boot($module1);
        $container = $package->container();

        // Registered by module-1, added via "boot"
        static::assertTrue($container->has('a'));
        static::assertTrue($container->has('b'));

        // Registered by module-2, which is added when service "a" is added
        static::assertTrue($container->has('c'));
        static::assertTrue($container->has('d'));

        // Registered by module-3, which should NOT be added, because service "meh" is not there
        static::assertFalse($container->has('e'));
        static::assertFalse($container->has('f'));
    }

    /**
     * Test we can use "after get" listeners to interact with created services.
     *
     * @test
     */
    public function testAfterGetListenersCanExtendsObjects(): void
    {
        $loggerModule = $this->mockModule('logger-module', ServiceModule::class);
        $loggerModule->shouldReceive('services')->andReturn([
            'logger' => static function () {
                return new class {
                    public $logs = [];
                    public function log(string $message): void {
                        $this->logs[] = $message;
                    }
                };
            }
        ]);

        $module1 = $this->mockModule('module-1', ServiceModule::class);
        $module1->shouldReceive('services')->andReturn([
            'logger-aware-1' => static function () {
                return new class {
                    public $logger;
                    public function setLogger($logger) {
                        $this->logger = $logger;
                    }
                    public function log(string $message) {
                        $this->logger->log("[ONE] {$message}");
                    }
                };
            },
            'logger-aware-2' => static function () {
                return new class {
                    public $logger;
                    public function setLogger($logger) {
                        $this->logger = $logger;
                    }
                    public function log(string $message) {
                        $this->logger->log("[TWO] {$message}");
                    }
                };
            }
        ]);

        $module2 = $this->mockModule('module-2', ServiceModule::class);
        $module2->shouldReceive('services')->andReturn([
            'logger-aware-3' => static function () {
                return new class {
                    public $logger;
                    public function setLogger($logger) {
                        $this->logger = $logger;
                    }
                    public function log(string $message) {
                        $this->logger->log("[THREE] {$message}");
                    }
                };
            },
            'logger-aware-4' => static function () {
                return new class {
                    public $logger;
                    public function setLogger($logger) {
                        $this->logger = $logger;
                    }
                    public function log(string $message) {
                        $this->logger->log("[FOUR] {$message}");
                    }
                };
            }
        ]);

        $listener = new class() implements ListeningModule
        {
            use ModuleClassNameIdTrait;

            public $collector = [];

            public function listen(ServiceEvent $event): void
            {
                if (
                    ($event instanceof AfterServiceResolved)
                    && (stripos($event->serviceId(), 'logger-aware-') === 0)
                ) {
                    $event->service()->setLogger($event->container()->get('logger'));
                    $this->collector[] = $event->serviceId();
                }
            }
        };

        $package = Package::new($this->mockProperties('test-package', false))
            ->addModule($module1)
            ->addModule($listener)
            ->addModule($loggerModule)
            ->addModule($module2);

        $package->boot();
        $container = $package->container();

        $two = $container->get('logger-aware-2');
        $one = $container->get('logger-aware-1');
        $four = $container->get('logger-aware-4');
        $oneAgain = $container->get('logger-aware-1');
        $fourAgain = $container->get('logger-aware-4');
        $oneAgainAgain = $container->get('logger-aware-1');
        $twoAgain = $container->get('logger-aware-2');

        static::assertSame($one, $oneAgain);
        static::assertSame($one, $oneAgainAgain);
        static::assertSame($two, $twoAgain);
        static::assertSame($four, $fourAgain);

        // No matter how many time we retrieve the service, listener is called once.
        // Moreover, listener is not called for "logger-aware-3" service which is never retrieved.
        static::assertSame(
            ['logger-aware-2', 'logger-aware-1', 'logger-aware-4'],
            $listener->collector
        );

        $one->log('Test!');
        $two->log('Test!');
        $four->log('Test!');

        static::assertSame(
            ['[ONE] Test!', '[TWO] Test!', '[FOUR] Test!'],
            $container->get('logger')->logs
        );
    }

    /**
     * Test each listener has the possibility to stop the rest of listeners for the same event.
     *
     * @test
     */
    public function testListenersCanBeStopped(): void
    {
        $module = $this->mockModule('test-module', ServiceModule::class);
        $module->expects('services')->andReturn($this->stubServices('foo', 'bar', 'baz'));

        $listener = new class() implements Module, ListenerProviderInterface
        {
            use ModuleClassNameIdTrait;

            public $before = '';
            public $after = '';

            public function getListenersForEvent(object $event): iterable
            {
                if ($event instanceof BeforeServiceResolved) {
                    return [
                        function (): void {
                            $this->before .= 'a';
                        },
                        function (): void {
                            $this->before .= 'b';
                        },
                        function (BeforeServiceResolved $event): void {
                            $event->stop();
                            $this->before .= 'c-';
                        },
                        function (): void {
                            $this->before .= 'd';
                        },
                        function (): void {
                            $this->before .= 'e';
                        }
                    ];
                }

                if ($event instanceof AfterServiceResolved) {
                    return [
                        function (): void {
                            $this->after .= 'a';
                        },
                        function (AfterServiceResolved $event): void {
                            $this->after .= '-';
                            $event->stop();
                        }
                    ];
                }

                return [];
            }
        };

        $package = Package::new($this->mockProperties('test-package', false))
            ->addModule($module)
            ->addModule($listener);

        $package->boot();
        $container = $package->container();
        $container->get('foo');
        $container->get('bar');

        static::assertSame('abc-abc-', $listener->before);
        static::assertSame('a-a-', $listener->after);
    }

    /**
     * Test "before register" listener can intercept service overrides.
     *
     * @test
     */
    public function testListenersCanInterceptServiceOverrides(): void
    {
        $listener = new class implements ListeningModule
        {
            use ModuleClassNameIdTrait;

            public $collector = '';

            public function listen(ServiceEvent $event): void
            {
                if (
                    ($event instanceof BeforeServiceAdded)
                    && ($event->type() === ServiceEvent::BEFORE_OVERRIDE)
                ) {
                    $this->collector = sprintf(
                        "Overriding service '%s' via module '%s'.",
                        $event->serviceId(),
                        $event->moduleId()
                    );
                }
            }
        };

        $module1 = $this->mockModule('module-1', ServiceModule::class);
        $module1->expects('services')->andReturn($this->stubServices('a', 'b', 'c'));

        $module2 = $this->mockModule('module-2', ServiceModule::class);
        $module2->expects('services')->andReturn($this->stubServices('d', 'a', 'e'));

        Package::new($this->mockProperties('test-package', false))
            ->addModule($listener)
            ->addModule($module1)
            ->addModule($module2);

        static::assertSame(
            $listener->collector,
            "Overriding service 'a' via module 'module-2'."
        );
    }
}
