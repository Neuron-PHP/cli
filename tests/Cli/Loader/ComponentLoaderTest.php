<?php

namespace Tests\Cli\Loader;

use Neuron\Cli\Loader\ComponentLoader;
use Neuron\Cli\Commands\Registry;
use Neuron\Core\System\MemoryFileSystem;
use PHPUnit\Framework\TestCase;

class ComponentLoaderTest extends TestCase
{
	private Registry $registry;
	private MemoryFileSystem $fs;

	protected function setUp(): void
	{
		$this->registry = new Registry();
		$this->fs = new MemoryFileSystem();
	}

	public function testConstructor(): void
	{
		$loader = new ComponentLoader( $this->registry, $this->fs );
		$this->assertInstanceOf( ComponentLoader::class, $loader );
	}

	public function testGetLoadedProviders(): void
	{
		$loader = new ComponentLoader( $this->registry, $this->fs );
		$this->assertIsArray( $loader->getLoadedProviders() );
		$this->assertEmpty( $loader->getLoadedProviders() );
	}

	public function testLoadProviderWithValidClass(): void
	{
		$loader = new ComponentLoader( $this->registry, $this->fs );

		$result = $loader->loadProvider( MockCliProvider::class );

		$this->assertTrue( $result );
		$this->assertContains( MockCliProvider::class, $loader->getLoadedProviders() );
		$this->assertTrue( $this->registry->has( 'mock:test' ) );
	}

	public function testLoadProviderWithNonExistentClass(): void
	{
		$loader = new ComponentLoader( $this->registry, $this->fs );

		$result = $loader->loadProvider( 'NonExistent\Provider' );

		$this->assertFalse( $result );
		$this->assertEmpty( $loader->getLoadedProviders() );
	}

	public function testLoadProviderWithoutRegisterMethod(): void
	{
		$loader = new ComponentLoader( $this->registry, $this->fs );

		$result = $loader->loadProvider( MockProviderWithoutRegister::class );

		$this->assertFalse( $result );
		$this->assertEmpty( $loader->getLoadedProviders() );
	}

	public function testLoadProviderThatThrowsException(): void
	{
		$loader = new ComponentLoader( $this->registry, $this->fs );

		$result = $loader->loadProvider( MockProviderWithException::class );

		// Should return false and not break
		$this->assertFalse( $result );
		$this->assertEmpty( $loader->getLoadedProviders() );
	}

	public function testLoadProviderAvoidsDuplicates(): void
	{
		$loader = new ComponentLoader( $this->registry, $this->fs );

		$loader->loadProvider( MockCliProvider::class );
		$loader->loadProvider( MockCliProvider::class );

		$providers = $loader->getLoadedProviders();
		$this->assertCount( 1, $providers );
	}

	public function testRegisterProvider(): void
	{
		$loader = new ComponentLoader( $this->registry, $this->fs );

		$result = $loader->registerProvider( MockCliProvider::class );

		$this->assertTrue( $result );
		$this->assertContains( MockCliProvider::class, $loader->getLoadedProviders() );
	}

	public function testLoadComponentsWithNoVendorDirectory(): void
	{
		$loader = new ComponentLoader( $this->registry, $this->fs );

		// No vendor directory set up in MemoryFileSystem
		$loader->loadComponents();

		// Should not throw, just return silently
		$this->assertEmpty( $loader->getLoadedProviders() );
	}

	public function testLoadComponentsWithMissingInstalledJson(): void
	{
		// Set up vendor directory but no installed.json
		$vendorDir = dirname( __DIR__, 4 ) . '/vendor';
		$this->fs->addDirectory( $vendorDir );

		$loader = new ComponentLoader( $this->registry, $this->fs );
		$loader->loadComponents();

		$this->assertEmpty( $loader->getLoadedProviders() );
	}

	public function testLoadComponentsWithComposer2xFormat(): void
	{
		// Set up vendor directory structure matching ComponentLoader's search paths
		$vendorDir = dirname( __DIR__, 4 ) . '/vendor';
		$this->fs->addDirectory( $vendorDir );
		$this->fs->addDirectory( $vendorDir . '/composer' );

		// Add installed.json with composer 2.x format (has 'packages' key)
		$installedJson = $vendorDir . '/composer/installed.json';
		$installed = [
			'packages' => [
				[
					'name' => 'neuron-php/test',
					'version' => '1.0.0',
					'extra' => [
						'neuron' => [
							'cli-provider' => MockCliProvider::class
						]
					]
				],
				[
					'name' => 'other-vendor/package',
					'version' => '1.0.0',
					'extra' => [
						'neuron' => [
							'cli-provider' => 'Some\Provider'
						]
					]
				]
			]
		];
		$this->fs->addFile( $installedJson, json_encode( $installed ) );

		$loader = new ComponentLoader( $this->registry, $this->fs );
		$loader->loadComponents();

		// Should load MockCliProvider from neuron-php package
		$this->assertContains( MockCliProvider::class, $loader->getLoadedProviders() );
		$this->assertTrue( $this->registry->has( 'mock:test' ) );
	}

	public function testLoadComponentsWithComposer1xFormat(): void
	{
		// Set up vendor directory structure
		$vendorDir = dirname( __DIR__, 4 ) . '/vendor';
		$this->fs->addDirectory( $vendorDir );
		$this->fs->addDirectory( $vendorDir . '/composer' );

		// Add installed.json with composer 1.x format (no 'packages' key)
		$installedJson = $vendorDir . '/composer/installed.json';
		$installed = [
			[
				'name' => 'neuron-php/cli',
				'version' => '1.0.0',
				'extra' => [
					'neuron' => [
						'cli-provider' => MockCliProvider::class
					]
				]
			]
		];
		$this->fs->addFile( $installedJson, json_encode( $installed ) );

		$loader = new ComponentLoader( $this->registry, $this->fs );
		$loader->loadComponents();

		$this->assertContains( MockCliProvider::class, $loader->getLoadedProviders() );
	}

	public function testLoadComponentsFiltersNonNeuronPackages(): void
	{
		$vendorDir = dirname( __DIR__, 4 ) . '/vendor';
		$this->fs->addDirectory( $vendorDir );
		$this->fs->addDirectory( $vendorDir . '/composer' );

		$installedJson = $vendorDir . '/composer/installed.json';
		$installed = [
			[
				'name' => 'symfony/console',
				'version' => '5.0.0',
				'extra' => [
					'neuron' => [
						'cli-provider' => 'Symfony\Provider'
					]
				]
			],
			[
				'name' => 'neuron-php/test',
				'version' => '1.0.0',
				'extra' => [
					'neuron' => [
						'cli-provider' => MockCliProvider::class
					]
				]
			]
		];
		$this->fs->addFile( $installedJson, json_encode( $installed ) );

		$loader = new ComponentLoader( $this->registry, $this->fs );
		$loader->loadComponents();

		// Should only load neuron-php provider
		$this->assertCount( 1, $loader->getLoadedProviders() );
		$this->assertContains( MockCliProvider::class, $loader->getLoadedProviders() );
	}

	public function testLoadComponentsWithInvalidJson(): void
	{
		$vendorDir = dirname( __DIR__, 4 ) . '/vendor';
		$this->fs->addDirectory( $vendorDir );
		$this->fs->addDirectory( $vendorDir . '/composer' );

		$installedJson = $vendorDir . '/composer/installed.json';
		$this->fs->addFile( $installedJson, 'invalid json {{{' );

		$loader = new ComponentLoader( $this->registry, $this->fs );
		$loader->loadComponents();

		// Should handle gracefully
		$this->assertEmpty( $loader->getLoadedProviders() );
	}

	public function skippedTestLoadProjectCommandsFromComposerJson(): void
	{
		// Add project composer.json at location that findComposerJson() checks
		$composerJson = dirname( __DIR__, 4 ) . '/composer.json';
		$composer = [
			'name' => 'my/project',
			'extra' => [
				'neuron' => [
					'cli-provider' => MockCliProvider::class
				]
			]
		];
		$this->fs->addFile( $composerJson, json_encode( $composer ) );

		// Set up empty vendor directory so loadComponents() can proceed
		$vendorDir = dirname( __DIR__, 4 ) . '/vendor';
		$this->fs->addDirectory( $vendorDir );
		$this->fs->addDirectory( $vendorDir . '/composer' );
		$installedJson = $vendorDir . '/composer/installed.json';
		$this->fs->addFile( $installedJson, json_encode( [] ) );

		$loader = new ComponentLoader( $this->registry, $this->fs );
		$loader->loadComponents();

		// Should load provider from project composer.json
		$this->assertContains( MockCliProvider::class, $loader->getLoadedProviders() );
	}

	public function skippedTestLoadProjectCommandsWithDirectCommands(): void
	{
		// Add project composer.json at location that findComposerJson() checks
		$composerJson = dirname( __DIR__, 4 ) . '/composer.json';
		$composer = [
			'name' => 'my/project',
			'extra' => [
				'neuron' => [
					'commands' => [
						'project:test' => MockTestCommand::class
					]
				]
			]
		];
		$this->fs->addFile( $composerJson, json_encode( $composer ) );

		// Set up empty vendor directory
		$vendorDir = dirname( __DIR__, 4 ) . '/vendor';
		$this->fs->addDirectory( $vendorDir );
		$this->fs->addDirectory( $vendorDir . '/composer' );
		$installedJson = $vendorDir . '/composer/installed.json';
		$this->fs->addFile( $installedJson, json_encode( [] ) );

		$loader = new ComponentLoader( $this->registry, $this->fs );
		$loader->loadComponents();

		// Should register direct commands
		$this->assertTrue( $this->registry->has( 'project:test' ) );
	}

	public function testLoadProjectCommandsWithMissingComposerJson(): void
	{
		// No composer.json exists at any location

		// Set up vendor directory
		$vendorDir = dirname( __DIR__, 4 ) . '/vendor';
		$this->fs->addDirectory( $vendorDir );
		$this->fs->addDirectory( $vendorDir . '/composer' );
		$installedJson = $vendorDir . '/composer/installed.json';
		$this->fs->addFile( $installedJson, json_encode( [] ) );

		$loader = new ComponentLoader( $this->registry, $this->fs );
		$loader->loadComponents();

		// Should not throw, just return
		$this->assertEmpty( $loader->getLoadedProviders() );
	}

	public function testFindVendorDirectoryInCwd(): void
	{
		$this->fs->setCwd( '/project' );
		$vendorDir = '/project/vendor';
		$this->fs->addDirectory( $vendorDir );
		$this->fs->addDirectory( $vendorDir . '/composer' );

		$installedJson = $vendorDir . '/composer/installed.json';
		$installed = [
			[
				'name' => 'neuron-php/test',
				'extra' => [
					'neuron' => [
						'cli-provider' => MockCliProvider::class
					]
				]
			]
		];
		$this->fs->addFile( $installedJson, json_encode( $installed ) );

		$loader = new ComponentLoader( $this->registry, $this->fs );
		$loader->loadComponents();

		// Should find vendor directory in cwd
		$this->assertContains( MockCliProvider::class, $loader->getLoadedProviders() );
	}
}

/**
 * Mock CLI provider for testing
 */
class MockCliProvider
{
	public static function register( Registry $registry ): void
	{
		$registry->register( 'mock:test', MockTestCommand::class );
	}
}

/**
 * Mock provider without register method
 */
class MockProviderWithoutRegister
{
	// No register method
}

/**
 * Mock provider that throws exception
 */
class MockProviderWithException
{
	public static function register( Registry $registry ): void
	{
		throw new \Exception( 'Provider error' );
	}
}

/**
 * Mock command for testing
 */
class MockTestCommand extends \Neuron\Cli\Commands\Command
{
	public function getName(): string
	{
		return 'mock:test';
	}

	public function getDescription(): string
	{
		return 'Mock test command';
	}

	public function configure(): void
	{
		// No configuration
	}

	public function execute(): int
	{
		return 0;
	}
}
