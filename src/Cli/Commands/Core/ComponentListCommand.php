<?php

namespace Neuron\Cli\Commands\Core;

use Neuron\Cli\Commands\Command;
use Neuron\Cli\Commands\Registry;

/**
 * Lists all available commands organized by component
 */
class ComponentListCommand extends Command
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'list';
	}
	
	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'List all available commands';
	}
	
	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addOption( 'namespace', 'n', true, 'Filter commands by namespace' );
		$this->addOption( 'raw', 'r', false, 'Display raw command list without formatting' );
	}
	
	/**
	 * @inheritDoc
	 */
	public function execute(): int
	{
		// Get the application from registry
		$app = \Neuron\Patterns\Registry::getInstance()->get( 'cli.application' );
		
		if( !$app )
		{
			$this->output->error( "Application not found in registry" );
			return 1;
		}
		
		$registry = $app->getRegistry();
		$namespace = $this->input->getOption( 'namespace' );
		$raw = $this->input->getOption( 'raw' );
		
		if( $raw )
		{
			$this->displayRawList( $registry, $namespace );
		}
		else
		{
			$this->displayFormattedList( $registry, $namespace );
		}
		
		return 0;
	}
	
	/**
	 * Display formatted command list grouped by namespace
	 * 
	 * @param Registry $registry
	 * @param string|null $namespace
	 * @return void
	 */
	private function displayFormattedList( Registry $registry, ?string $namespace ): void
	{
		$this->output->title( "Neuron CLI Commands" );
		
		// Get commands grouped by namespace
		$namespaces = [];
		$commands = $registry->all();
		
		if( empty( $commands ) )
		{
			$this->output->warning( "No commands available" );
			return;
		}
		
		// Group commands by namespace
		foreach( $commands as $name => $class )
		{
			// Filter by namespace if specified
			if( $namespace !== null && !str_starts_with( $name, $namespace . ':' ) )
			{
				continue;
			}
			
			// Extract namespace
			$parts = explode( ':', $name );
			$ns = count( $parts ) > 1 ? $parts[0] : 'global';
			
			if( !isset( $namespaces[$ns] ) )
			{
				$namespaces[$ns] = [];
			}
			
			$namespaces[$ns][$name] = $class;
		}
		
		if( empty( $namespaces ) )
		{
			$this->output->warning( "No commands found" . ($namespace ? " in namespace '{$namespace}'" : "") );
			return;
		}
		
		// Sort namespaces, but keep 'global' first
		uksort( $namespaces, function($a, $b) {
			if( $a === 'global' ) return -1;
			if( $b === 'global' ) return 1;
			return strcmp( $a, $b );
		});
		
		// Display each namespace
		foreach( $namespaces as $ns => $nsCommands )
		{
			$this->output->section( ucfirst( $ns ) . " Commands" );
			
			// Load command instances to get descriptions
			$commandInfo = [];
			foreach( $nsCommands as $name => $class )
			{
				try
				{
					if( class_exists( $class ) )
					{
						$command = new $class();
						$commandInfo[] = [
							'name' => $name,
							'description' => $command->getDescription()
						];
					}
					else
					{
						$commandInfo[] = [
							'name' => $name,
							'description' => 'Command class not found'
						];
					}
				}
				catch( \Exception $e )
				{
					$commandInfo[] = [
						'name' => $name,
						'description' => 'Error loading command'
					];
				}
			}
			
			// Sort by name
			usort( $commandInfo, fn($a, $b) => strcmp( $a['name'], $b['name'] ) );
			
			// Display commands
			foreach( $commandInfo as $info )
			{
				$this->output->write( sprintf( 
					"  %-30s %s",
					$this->output->getVerbosity() > 0 ? $info['name'] : $info['name'],
					$info['description']
				));
			}
			
			$this->output->newLine();
		}
		
		// Display usage information
		$this->output->info( "Usage:" );
		$this->output->write( "  neuron <command> [options] [arguments]" );
		$this->output->write( "" );
		$this->output->info( "Options:" );
		$this->output->write( "  --help, -h     Display help for the given command" );
		$this->output->write( "  --version, -V  Display version information" );
		$this->output->write( "  --verbose, -v  Increase verbosity of messages" );
		$this->output->write( "  --quiet, -q    Do not output any message" );
	}
	
	/**
	 * Display raw command list
	 * 
	 * @param Registry $registry
	 * @param string|null $namespace
	 * @return void
	 */
	private function displayRawList( Registry $registry, ?string $namespace ): void
	{
		$commands = $registry->all();
		
		foreach( $commands as $name => $class )
		{
			// Filter by namespace if specified
			if( $namespace !== null && !str_starts_with( $name, $namespace . ':' ) )
			{
				continue;
			}
			
			$this->output->write( $name );
		}
	}
}