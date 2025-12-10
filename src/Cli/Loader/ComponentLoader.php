<?php

namespace Neuron\Cli\Loader;

use Neuron\Cli\Commands\Registry;
use Neuron\Core\System\IFileSystem;
use Neuron\Core\System\RealFileSystem;

/**
 * Loads commands from installed Neuron components.
 * Scans composer.json files for CLI provider classes
 * and registers their commands.
 */
class ComponentLoader
{
	private Registry $registry;
	private array $loadedProviders = [];
	private IFileSystem $fs;

	/**
	 * @param Registry $registry
	 * @param IFileSystem|null $fs File system implementation (null = use real file system)
	 */
	public function __construct( Registry $registry, ?IFileSystem $fs = null )
	{
		$this->registry = $registry;
		$this->fs = $fs ?? new RealFileSystem();
	}
	
	/**
	 * Load commands from all installed components
	 * 
	 * @return void
	 */
	public function loadComponents(): void
	{
		// Get the vendor directory
		$vendorDir = $this->findVendorDirectory();
		
		if( !$vendorDir )
		{
			return;
		}
		
		// Load components from composer's installed.json
		$installedJson = $vendorDir . '/composer/installed.json';

		if( !$this->fs->fileExists( $installedJson ) )
		{
			return;
		}

		$content = $this->fs->readFile( $installedJson );
		$installed = $content !== false ? json_decode( $content, true ) : null;
		
		if( !$installed )
		{
			return;
		}
		
		// Handle both composer 1.x and 2.x formats
		$packages = isset( $installed['packages'] ) ? $installed['packages'] : $installed;
		
		foreach( $packages as $package )
		{
			// Only process neuron-php packages
			if( !str_starts_with( $package['name'] ?? '', 'neuron-php/' ) )
			{
				continue;
			}
			
			// Check for CLI provider in extra section
			if( isset( $package['extra']['neuron']['cli-provider'] ) )
			{
				$this->loadProvider( $package['extra']['neuron']['cli-provider'] );
			}
		}
		
		// Also check the root project's composer.json
		$this->loadProjectCommands();
	}
	
	/**
	 * Load a command provider class
	 * 
	 * @param string $providerClass
	 * @return bool
	 */
	public function loadProvider( string $providerClass ): bool
	{
		// Avoid loading the same provider twice
		if( in_array( $providerClass, $this->loadedProviders ) )
		{
			return true;
		}
		
		if( !class_exists( $providerClass ) )
		{
			return false;
		}
		
		// Check if the provider has the register method
		if( !method_exists( $providerClass, 'register' ) )
		{
			return false;
		}
		
		try
		{
			// Call the static register method with the registry directly
			$providerClass::register( $this->registry );
			
			$this->loadedProviders[] = $providerClass;
			
			return true;
		}
		catch( \Exception $e )
		{
			// Silently fail - component might not be properly installed
			return false;
		}
	}
	
	/**
	 * Load commands from the project's composer.json
	 * 
	 * @return void
	 */
	private function loadProjectCommands(): void
	{
		$composerJson = $this->findComposerJson();

		if( !$composerJson || !$this->fs->fileExists( $composerJson ) )
		{
			return;
		}

		$content = $this->fs->readFile( $composerJson );
		$composer = $content !== false ? json_decode( $content, true ) : null;
		
		if( !$composer )
		{
			return;
		}
		
		// Check for CLI provider in extra section
		if( isset( $composer['extra']['neuron']['cli-provider'] ) )
		{
			$this->loadProvider( $composer['extra']['neuron']['cli-provider'] );
		}
		
		// Also check for direct command registrations
		if( isset( $composer['extra']['neuron']['commands'] ) && is_array( $composer['extra']['neuron']['commands'] ) )
		{
			foreach( $composer['extra']['neuron']['commands'] as $name => $class )
			{
				if( !$this->registry->has( $name ) )
				{
					$this->registry->register( $name, $class );
				}
			}
		}
	}
	
	/**
	 * Find the vendor directory
	 * 
	 * @return string|null
	 */
	private function findVendorDirectory(): ?string
	{
		$cwd = $this->fs->getcwd();

		// Try common locations
		$locations = [
			__DIR__ . '/../../../vendor',  // When installed as dependency
			__DIR__ . '/../../../../vendor', // When in development
			$cwd . '/vendor',          // Current directory
			dirname( __DIR__, 4 ) . '/vendor', // Alternative location
		];

		foreach( $locations as $location )
		{
			$path = $this->fs->realpath( $location );
			if( $path && $this->fs->isDir( $path ) )
			{
				return $path;
			}
		}

		return null;
	}
	
	/**
	 * Find the project's composer.json
	 * 
	 * @return string|null
	 */
	private function findComposerJson(): ?string
	{
		$cwd = $this->fs->getcwd();

		// Try common locations
		$locations = [
			$cwd . '/composer.json',
			dirname( __DIR__, 4 ) . '/composer.json',
			dirname( __DIR__, 5 ) . '/composer.json',
		];

		foreach( $locations as $location )
		{
			if( $this->fs->fileExists( $location ) )
			{
				return $location;
			}
		}

		return null;
	}
	
	/**
	 * Get the list of loaded providers
	 * 
	 * @return array
	 */
	public function getLoadedProviders(): array
	{
		return $this->loadedProviders;
	}
	
	/**
	 * Manually register a provider
	 * 
	 * @param string $providerClass
	 * @return bool
	 */
	public function registerProvider( string $providerClass ): bool
	{
		return $this->loadProvider( $providerClass );
	}
}