<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit;

use Inpsyde\Modularity\Package;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Properties\Properties;
use Inpsyde\Modularity\Tests\TestCase;
use Psr\Container\ContainerInterface;

class PackageTest extends TestCase
{
    /**
     * @test
     *
     * @throws \Throwable
     */
    public function testBasic(): void
    {
        $expectedName = 'foo';
        $propertiesStub = $this->mockProperties($expectedName);

        $testee = Package::new($propertiesStub);

        static::assertTrue($testee->statusIs(Package::STATUS_IDLE));
        static::assertTrue($testee->boot());
        static::assertTrue($testee->statusIs(Package::STATUS_BOOTED));
        static::assertSame($expectedName, $testee->name());
        static::assertInstanceOf(Properties::class, $testee->properties());
        static::assertInstanceOf(ContainerInterface::class, $testee->container());
        static::assertEmpty($testee->modulesStatus()[Package::MODULES_ALL]);
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
        $testee = Package::new($propertiesStub);
        static::assertSame($expectedHookName, $testee->hookName($suffix));
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
     *
     * @throws \Throwable
     */
    public function testBootWithModule(): void
    {
        $expectedModuleId = 'my-module';

        $moduleStub = $this->mockModule($expectedModuleId);
        $propertiesStub = $this->mockProperties();
        $propertiesStub->expects('isDebug')->andReturn(false);

        $testee = Package::new($propertiesStub);

        static::assertTrue($testee->boot($moduleStub));
        static::assertFalse($testee->moduleIs($expectedModuleId, Package::MODULE_ADDED));

        // booting again will do nothing.
        static::assertFalse($testee->boot());
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function testBootWithServiceModule(): void
    {
        $serviceModuleId = 'my-service-module';
        $serviceId = 'service-id';

        $serviceModuleStub = $this->mockServiceModule($serviceModuleId);
        $serviceModuleStub
            ->shouldReceive('services')
            ->andReturn(
                [
                    $serviceId => function () {
                        return new class() {
                            public function __toString()
                            {
                                return 'bar';
                            }
                        };
                    },
                ]
            );

        $propertiesStub = $this->mockProperties();
        $testee = Package::new($propertiesStub);

        static::assertTrue($testee->boot($serviceModuleStub));
        static::assertTrue($testee->moduleIs($serviceModuleId, Package::MODULE_ADDED));
        static::assertTrue($testee->moduleIs($serviceModuleId, Package::MODULE_REGISTERED));
        static::assertTrue($testee->container()->has($serviceId));
    }

    /**
     * @test
     */
    public function testBootWithFactoryModule(): void
    {
        $factoryModuleId = 'my-factory-module';
        $factoryId = 'factory-id';

        $factoryModuleStub = $this->mockFactoryModule($factoryModuleId);
        $factoryModuleStub
            ->shouldReceive('factories')
            ->andReturn(
                [
                    $factoryId => function () {
                        return new class() {
                            public function __toString()
                            {
                                return 'foo';
                            }
                        };
                    },
                ]
            );

        $propertiesStub = $this->mockProperties();
        $testee = Package::new($propertiesStub);

        static::assertTrue($testee->boot($factoryModuleStub));
        static::assertTrue($testee->moduleIs($factoryModuleId, Package::MODULE_ADDED));
        static::assertTrue($testee->moduleIs($factoryModuleId, Package::MODULE_REGISTERED));
        static::assertTrue($testee->container()->has($factoryId));
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function testBootWithExtendingModule(): void
    {
        $extendingModuleId = 'my-extending-module';
        $serviceId = 'service-1';

        $extendingModuleStub = $this->mockExtendingModule($extendingModuleId);
        $extendingModuleStub
            ->shouldReceive('extensions')
            ->andReturn(
                [
                    $serviceId => function () {
                        return 'foo';
                    },
                ]
            );

        $propertiesStub = $this->mockProperties();

        $testee = Package::new($propertiesStub);

        static::assertTrue($testee->boot($extendingModuleStub));
        static::assertTrue($testee->moduleIs($extendingModuleId, Package::MODULE_ADDED));
        static::assertTrue($testee->moduleIs($extendingModuleId, Package::MODULE_EXTENDED));
    }

    /**
     * Test if on Properties::isDebug() === false no Exception is thrown
     * and Boostrap::boot() returns false.
     *
     * @test
     */
    public function testBootWithThrowingModuleAndDebugFalse(): void
    {
        $throwingModule = new class implements ExecutableModule {
            use ModuleClassNameIdTrait;

            public function run(ContainerInterface $container): bool
            {
                throw new \Exception("Catch me if you can!");
            }
        };

        $properties = $this->mockProperties();
        $properties->expects('isDebug')->andReturn(false);
        $testee = Package::new($properties);

        static::assertFalse($testee->boot($throwingModule));
        static::assertTrue($testee->statusIs(Package::STATUS_FAILED));
    }

    /**
     * Test if on Properties::isDebug() === true an Exception is thrown.
     *
     * @test
     */
    public function testBootWithThrowingModuleAndDebugTrue(): void
    {
        static::expectException(\Exception::class);

        $throwingModule = new class implements ExecutableModule {
            use ModuleClassNameIdTrait;

            public function run(ContainerInterface $container): bool
            {
                throw new \Exception("Catch me if you can!");
            }
        };

        $properties = $this->mockProperties();
        $properties->expects('isDebug')->andReturn(true);
        Package::new($properties)->boot($throwingModule);
    }

    /**
     * @test
     */
    public function testBootWithExecutableModule(): void
    {
        $serviceId = 'executable-module';
        $executableModule = $this->mockExecutableModule($serviceId);
        $executableModule->shouldReceive('run')
            ->andReturn(true);

        $properties = $this->mockProperties();

        $testee = Package::new($properties);

        static::assertTrue($testee->boot($executableModule));
        static::assertTrue($testee->moduleIs($serviceId, Package::MODULE_EXECUTED));
    }

    /**
     * Test, when the ExecutableModule::run() return false, that the state is correctly set.
     *
     * @test
     */
    public function testBootWithExecutableModuleFailed(): void
    {
        $serviceId = 'executable-module';
        $executableModule = $this->mockExecutableModule($serviceId);
        $executableModule->shouldReceive('run')
            ->andReturn(false);

        $properties = $this->mockProperties();
        $testee = Package::new($properties);

        static::assertTrue($testee->boot($executableModule));
        static::assertTrue($testee->moduleIs($serviceId, Package::MODULE_EXECUTION_FAILED));
    }
}
