<?php

declare(strict_types=1);

namespace Spiral\Testing;

use Closure;
use JetBrains\PhpStorm\ArrayShape;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Spiral\Boot\AbstractKernel;
use Spiral\Boot\Environment;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Core\Container;

abstract class TestCase extends BaseTestCase
{
    use Traits\InteractsWithConsole,
        Traits\InteractsWithHttp,
        Traits\InteractsWithCore,
        Traits\InteractsWithFileSystem,
        Traits\InteractsWithConfig,
        Traits\InteractsWithDispatcher,
        Traits\InteractsWithMailer,
        Traits\InteractsWithQueue,
        MockeryPHPUnitIntegration;

    public const ENV = [];

    private TestableKernelInterface $app;
    /** @var array<Closure> */
    private array $beforeStarting = [];
    private ?EnvironmentInterface $environment = null;

    /**
     * @return array<class-string>|array<class-string, array<non-empty-string, mixed>>
     */
    public function defineBootloaders(): array
    {
        return [];
    }

    public function defineDirectories(string $root): array
    {
        return [
            'root' => $root,
            'app' => $root.'/app',
            'runtime' => $root.'/runtime',
            'cache' => $root.'/runtime/cache',
        ];
    }

    public function rootDirectory(): string
    {
        return dirname(__DIR__);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshApp();
    }

    final public function beforeStarting(Closure $callback): void
    {
        $this->beforeStarting[] = $callback;
    }

    final public function getApp(): TestApp
    {
        return $this->app;
    }

    final public function getContainer(): Container
    {
        return $this->app->getContainer();
    }

    public function createAppInstance(): TestableKernelInterface
    {
        return TestApp::createWithBootloaders(
            $this->defineBootloaders(),
            $this->defineDirectories(
                $this->rootDirectory()
            ),
            false
        );
    }

    /**
     * @param array<non-empty-string,mixed> $env
     * @return AbstractKernel|TestableKernelInterface
     */
    protected function initApp(array $env = []): AbstractKernel
    {
        $environment = new Environment($env);

        $app = $this->createAppInstance();
        $app->getContainer()->bindSingleton(EnvironmentInterface::class, $environment);

        $app->starting(...$this->beforeStarting);
        $app->run($environment);

        return $app;
    }

    protected function refreshApp()
    {
        $this->app = $this->initApp(static::ENV);
    }

    public function runScoped(Closure $callback, array $bindings = []): mixed
    {
        if ($this->environment) {
            $bindings[EnvironmentInterface::class] = $this->environment;
        }

        return $this->getContainer()->runScope($bindings, $callback);
    }
}
