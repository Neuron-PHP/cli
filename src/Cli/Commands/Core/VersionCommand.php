<?php

namespace Neuron\Cli\Commands\Core;

use Neuron\Cli\Commands\Command;
use Neuron\Core\System\IFileSystem;
use Neuron\Core\System\RealFileSystem;

/**
 * Version command - displays version information for Neuron components
 */
class VersionCommand extends Command
{
	private IFileSystem $fs;

	/**
	 * @param IFileSystem|null $fs File system implementation (null = use real file system)
	 */
	public function __construct( ?IFileSystem $fs = null )
	{
		$this->fs = $fs ?? new RealFileSystem();
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'version';
	}
	
	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Display version information';
	}
	
	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addOption( 'verbose', 'v', false, 'Show detailed version information for all components' );
		$this->addOption( 'component', 'c', true, 'Show version for a specific component' );
	}
	
	/**
	 * @inheritDoc
	 */
	public function execute(): int
	{
		$verbose = $this->input->getOption( 'verbose' );
		$component = $this->input->getOption( 'component' );
		
		if( $component )
		{
			$this->showComponentVersion( $component );
		}
		elseif( $verbose )
		{
			$this->showAllVersions();
		}
		else
		{
			$this->showMainVersion();
		}
		
		return 0;
	}
	
	/**
	 * Show the main Neuron CLI version
	 * 
	 * @return void
	 */
	private function showMainVersion(): void
	{
		$this->output->title( "Neuron CLI" );
		
		// Try to get version from composer.json
		$version = $this->getCliVersion();
		
		$this->output->info( "Version: {$version}" );
		$this->output->info( "PHP Version: " . PHP_VERSION );
		$this->output->write( "" );
		$this->output->comment( "Use --verbose to see all component versions" );
	}
	
	/**
	 * Show versions for all installed components
	 * 
	 * @return void
	 */
	private function showAllVersions(): void
	{
		$this->output->title( "Neuron Component Versions" );
		
		$components = $this->getInstalledComponents();
		
		if( empty( $components ) )
		{
			$this->output->warning( "No Neuron components found" );
			return;
		}
		
		$headers = ['Component', 'Version', 'Description'];
		$rows = [];
		
		foreach( $components as $name => $info )
		{
			$rows[] = [
				$name,
				$info['version'] ?? 'unknown',
				$info['description'] ?? ''
			];
		}
		
		$this->output->table( $headers, $rows );
	}
	
	/**
	 * Show version for a specific component
	 * 
	 * @param string $componentName
	 * @return void
	 */
	private function showComponentVersion( string $componentName ): void
	{
		$components = $this->getInstalledComponents();
		
		// Handle both with and without 'neuron-php/' prefix
		$searchName = str_starts_with( $componentName, 'neuron-php/' ) 
			? $componentName 
			: 'neuron-php/' . $componentName;
		
		if( !isset( $components[$searchName] ) )
		{
			$this->output->error( "Component '{$componentName}' not found" );
			$this->output->info( "Use 'neuron component:list' to see available components" );
			return;
		}
		
		$info = $components[$searchName];
		
		$this->output->title( "Component: {$searchName}" );
		$this->output->info( "Version: " . ($info['version'] ?? 'unknown') );
		
		if( isset( $info['description'] ) )
		{
			$this->output->info( "Description: {$info['description']}" );
		}
		
		if( isset( $info['authors'] ) && is_array( $info['authors'] ) )
		{
			$authors = array_map( function($author) {
				return $author['name'] ?? 'Unknown';
			}, $info['authors'] );
			$this->output->info( "Authors: " . implode( ', ', $authors ) );
		}
		
		if( isset( $info['license'] ) )
		{
			$license = is_array( $info['license'] ) 
				? implode( ', ', $info['license'] )
				: $info['license'];
			$this->output->info( "License: {$license}" );
		}
	}
	
	/**
	 * Get the CLI component version
	 *
	 * @return string
	 */
	private function getCliVersion(): string
	{
		$composerJson = dirname( __DIR__, 4 ) . '/composer.json';

		if( $this->fs->fileExists( $composerJson ) )
		{
			$content = $this->fs->readFile( $composerJson );
			if( $content !== false )
			{
				$composer = json_decode( $content, true );
				return $composer['version'] ?? '0.1.0';
			}
		}

		return '0.1.0';
	}
	
	/**
	 * Get information about installed Neuron components
	 *
	 * @return array
	 */
	private function getInstalledComponents(): array
	{
		$components = [];

		// Find vendor directory
		$vendorDir = $this->findVendorDirectory();

		if( !$vendorDir )
		{
			return $components;
		}

		// Load from composer's installed.json
		$installedJson = $vendorDir . '/composer/installed.json';

		if( !$this->fs->fileExists( $installedJson ) )
		{
			return $components;
		}

		$content = $this->fs->readFile( $installedJson );

		if( $content === false )
		{
			return $components;
		}

		$installed = json_decode( $content, true );

		if( !$installed )
		{
			return $components;
		}

		// Handle both composer 1.x and 2.x formats
		$packages = isset( $installed['packages'] ) ? $installed['packages'] : $installed;

		foreach( $packages as $package )
		{
			// Only include neuron-php packages
			if( str_starts_with( $package['name'] ?? '', 'neuron-php/' ) )
			{
				$components[$package['name']] = $package;
			}
		}

		// Sort by name
		ksort( $components );

		return $components;
	}
	
	/**
	 * Find the vendor directory
	 *
	 * @return string|null
	 */
	private function findVendorDirectory(): ?string
	{
		$cwd = $this->fs->getcwd();

		$locations = [
			dirname( __DIR__, 4 ) . '/vendor',
			dirname( __DIR__, 5 ) . '/vendor',
			$cwd . '/vendor',
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
}
