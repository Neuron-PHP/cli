<?php

namespace Neuron\Cli;

use Neuron\Application\Base;
use Neuron\Cli\Commands\Registry;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cli\Loader\ComponentLoader;
use Neuron\Data\Setting\Source\ISettingSource;
use Neuron\Log\Log;

/**
 * Comprehensive CLI application framework for the Neuron ecosystem.
 * 
 * This class serves as the central command-line interface for all Neuron
 * framework components, providing unified command discovery, registration,
 * execution, and management. It extends the base application framework
 * to provide CLI-specific functionality with automatic component loading
 * and modern command pattern implementation.
 * 
 * Key features:
 * - Automatic component discovery and command registration
 * - Extensible command registry with namespace support
 * - Rich console I/O with formatting and progress indicators
 * - Global option handling (--help, --version, --verbose)
 * - Error handling with detailed debugging information
 * - Exit code management for scripting integration
 * - Component isolation and lazy loading
 * 
 * The application supports both core framework commands and component-specific
 * commands, enabling a unified CLI experience across all Neuron modules.
 * Commands are automatically discovered from installed components and
 * registered with namespace prefixes (e.g., cms:init, mvc:routes).
 * 
 * @package Neuron\Cli
 * 
 * @example
 * ```php
 * // Basic CLI application setup
 * $app = new Application('1.0.0');
 * 
 * // Register custom command
 * $app->register('deploy:staging', DeployStagingCommand::class);
 * 
 * // Run the application
 * $app->run();
 * 
 * // Get exit code for scripting
 * exit($app->getExitCode());
 * ```
 */
class Application extends Base
{
	private Registry $commandRegistry;
	private ComponentLoader $componentLoader;
	private Input $input;
	private Output $output;
	private array $argv;
	private string $commandName = '';
	private ?Commands\Command $currentCommand = null;

	/**
	 * @param string $Version
	 * @param ISettingSource|null $Source
	 * @throws \Exception
	 */
	public function __construct( string $Version, ?ISettingSource $Source = null )
	{
		parent::__construct( $Version, $Source );
		
		$this->commandRegistry = new Registry();
		$this->componentLoader = new ComponentLoader( $this->commandRegistry );
		$this->output = new Output();
		
		// Register core commands
		$this->registerCoreCommands();
	}


	/**
	 * Register a command with the CLI application
	 * 
	 * @param string $name Command name (e.g., 'cms:init')
	 * @param string $class Fully qualified class name of the command
	 * @return void
	 */
	public function register( string $name, string $class ): void
	{
		$this->commandRegistry->register( $name, $class );
	}

	/**
	 * Check if a command exists
	 * 
	 * @param string $name
	 * @return bool
	 */
	public function has( string $name ): bool
	{
		return $this->commandRegistry->has( $name );
	}

	/**
	 * Get the command registry
	 * 
	 * @return Registry
	 */
	public function getRegistry(): Registry
	{
		return $this->commandRegistry;
	}

	/**
	 * Initialize the CLI application before run
	 * 
	 * @return bool
	 */
	protected function onStart(): bool
	{
		// Don't initialize events for CLI
		// Skip parent onStart to avoid event system initialization
		return true;
	}

	/**
	 * Execute the specified command
	 * 
	 * @return void
	 */
	protected function executeCommand(): void
	{
		// Store the application instance in the registry for commands to access
		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', $this );
		
		// Check if command exists
		if( !$this->commandRegistry->has( $this->commandName ) )
		{
			$this->output->error( "Command not found: {$this->commandName}" );
			$this->output->info( "Run 'neuron list' to see available commands." );
			return;
		}
		
		try
		{
			// Get and instantiate the command
			$commandClass = $this->commandRegistry->get( $this->commandName );
			
			if( !class_exists( $commandClass ) )
			{
				$this->output->error( "Command class not found: {$commandClass}" );
				return;
			}
			
			/** @var Commands\Command $command */
			$command = new $commandClass();
			$this->currentCommand = $command;
			
			// Remove command name from input
			array_shift( $this->argv );
			$this->input = new Input( $this->argv );
			
			// Set input and output on the command
			$command->setInput( $this->input );
			$command->setOutput( $this->output );
			
			// Configure the command (add arguments and options)
			$command->configure();
			
			// Parse input according to command configuration
			$this->input->parse( $command );
			
			// Execute the command
			$exitCode = $command->execute();
			
			// Store exit code in registry for bin/neuron to retrieve
			\Neuron\Patterns\Registry::getInstance()->set( 'cli.exit_code', $exitCode );
		}
		catch( \Exception $e )
		{
			$this->output->error( "Error executing command: " . $e->getMessage() );
			
			if( $this->input->hasOption( 'verbose' ) || $this->input->hasOption( 'v' ) )
			{
				$this->output->write( $e->getTraceAsString() );
			}
			
			// Store error exit code
			\Neuron\Patterns\Registry::getInstance()->set( 'cli.exit_code', 1 );
		}
	}

	/**
	 * Main execution of the CLI application
	 * 
	 * @return void
	 */
	protected function onRun(): void
	{
		$this->argv = $this->getParameters();
		
		// Remove script name from argv
		if( count( $this->argv ) > 0 && str_ends_with( $this->argv[0], 'neuron' ) )
		{
			array_shift( $this->argv );
		}
		
		// Create input from remaining arguments
		$this->input = new Input( $this->argv );
		
		// Load components and their commands
		try
		{
			$this->componentLoader->loadComponents();
		}
		catch( \Exception $e )
		{
			$this->output->error( "Failed to load components: " . $e->getMessage() );
			Log::error( "Component loading failed: " . $e->getMessage() );
		}
		
		// Check for help flags
		if( $this->input->hasOption( 'help' ) || $this->input->hasOption( 'h' ) )
		{
			if( !empty( $this->argv ) && !str_starts_with( $this->argv[0], '-' ) )
			{
				// Help for specific command
				$this->commandName = $this->argv[0];
				$this->showCommandHelp( $this->commandName );
			}
			else
			{
				// General help
				$this->showHelp();
			}
			return;
		}
		
		// Check for version flag
		if( $this->input->hasOption( 'version' ) || $this->input->hasOption( 'V' ) )
		{
			$this->showVersion();
			return;
		}
		
		// Check if we have a command to execute
		if( count( $this->argv ) > 0 && !str_starts_with( $this->argv[0], '-' ) )
		{
			$this->commandName = $this->argv[0];
			$this->executeCommand();
		}
		else
		{
			// No command specified, show help
			$this->showHelp();
		}
	}

	/**
	 * Register core commands that are always available
	 * 
	 * @return void
	 */
    private function registerCoreCommands(): void
    {
        $this->register( 'help', Commands\Core\HelpCommand::class );
        $this->register( 'version', Commands\Core\VersionCommand::class );
        $this->register( 'list', Commands\Core\ComponentListCommand::class );
        $this->register( 'config:env', Commands\Core\ConfigEnvCommand::class );
    }

	/**
	 * Show general help information
	 * 
	 * @return void
	 */
	private function showHelp(): void
	{
		$this->output->success( "Neuron CLI v{$this->getVersion()}" );
		$this->output->write( "" );
		$this->output->write( "Neuron CLI - Unified command-line interface for the Neuron PHP framework." );
		$this->output->write( "Use 'neuron list' to see all available commands or 'neuron help <command>' for specific help." );
		$this->output->write( "" );
		$this->output->info( "Usage:" );
		$this->output->write( "  neuron <command> [options] [arguments]" );
		$this->output->write( "" );
		$this->output->info( "Examples:" );
		$this->output->write( "  neuron list                    List all available commands" );
		$this->output->write( "  neuron help <command>          Get help for a specific command" );
		$this->output->write( "  neuron cms:init --theme=blog   Initialize CMS with blog theme" );
		$this->output->write( "" );
		$this->output->info( "Global Options:" );
		$this->output->write( "  --help, -h     Display help information" );
		$this->output->write( "  --version, -V  Display version information" );
		$this->output->write( "  --verbose, -v  Increase verbosity of output" );
	}
	
	/**
	 * Show help for a specific command
	 * 
	 * @param string $commandName
	 * @return void
	 */
	private function showCommandHelp( string $commandName ): void
	{
		// Store the application instance in the registry for commands to access
		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', $this );
		
		if( !$this->commandRegistry->has( $commandName ) )
		{
			$this->output->error( "Command '{$commandName}' not found" );
			$this->output->info( "Run 'neuron list' to see available commands" );
			return;
		}
		
		try
		{
			$commandClass = $this->commandRegistry->get( $commandName );
			
			if( !class_exists( $commandClass ) )
			{
				$this->output->error( "Command class not found: {$commandClass}" );
				return;
			}
			
			/** @var Commands\Command $command */
			$command = new $commandClass();
			$command->configure();
			
			$this->output->title( "Help: {$commandName}" );
			$this->output->write( $command->getHelp() );
		}
		catch( \Exception $e )
		{
			$this->output->error( "Error loading command: " . $e->getMessage() );
		}
	}
	
	/**
	 * Show version information
	 * 
	 * @return void
	 */
	private function showVersion(): void
	{
		$this->output->title( "Neuron CLI" );
		$this->output->info( "Version: {$this->getVersion()}" );
		$this->output->info( "PHP Version: " . PHP_VERSION );
	}
	
	/**
	 * Get the current command being executed
	 * 
	 * @return Commands\Command|null
	 */
	public function getCurrentCommand(): ?Commands\Command
	{
		return $this->currentCommand;
	}
	
	/**
	 * Get the exit code from the last command execution
	 * 
	 * @return int
	 */
	public function getExitCode(): int
	{
		return \Neuron\Patterns\Registry::getInstance()->get( 'cli.exit_code' ) ?? 0;
	}
}
