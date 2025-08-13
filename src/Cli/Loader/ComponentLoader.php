<?php

namespace Neuron\Cli\Loader;

use Neuron\Cli\Commands\Registry;

/**
 * Loads commands from installed Neuron components.
 * Scans composer.json files for CLI provider classes
 * and registers their commands.
 */
class ComponentLoader
{
	private Registry $registry;
	private array $loadedProviders = [];
	
	/**
	 * @param Registry $registry
	 */
	public function __construct( Registry $registry )
	{
		$this->registry = $registry;
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
		
		if( !file_exists( $installedJson ) )
		{
			return;
		}
		
		$installed = json_decode( file_get_contents( $installedJson ), true );
		
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
			// Create a simple wrapper application for registration
			$app = new class( $this->registry ) {
				private Registry $registry;
				
				public function __construct( Registry $registry )
				{
					$this->registry = $registry;
				}
				
				public function register( string $name, string $class ): void
				{
					// Silently skip if already registered
					if( !$this->registry->has( $name ) )
					{
						$this->registry->register( $name, $class );
					}
				}
			};
			
			// Call the static register method
			$providerClass::register( $app );
			
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
		
		if( !$composerJson || !file_exists( $composerJson ) )
		{
			return;
		}
		
		$composer = json_decode( file_get_contents( $composerJson ), true );
		
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
		// Try common locations
		$locations = [
			__DIR__ . '/../../../vendor',  // When installed as dependency
			__DIR__ . '/../../../../vendor', // When in development
			getcwd() . '/vendor',          // Current directory
			dirname( __DIR__, 4 ) . '/vendor', // Alternative location
		];
		
		foreach( $locations as $location )
		{
			$path = realpath( $location );
			if( $path && is_dir( $path ) )
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
		// Try common locations
		$locations = [
			getcwd() . '/composer.json',
			dirname( __DIR__, 4 ) . '/composer.json',
			dirname( __DIR__, 5 ) . '/composer.json',
		];
		
		foreach( $locations as $location )
		{
			if( file_exists( $location ) )
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