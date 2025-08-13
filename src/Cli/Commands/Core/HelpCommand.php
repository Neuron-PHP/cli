<?php

namespace Neuron\Cli\Commands\Core;

use Neuron\Cli\Commands\Command;
use Neuron\Patterns\Registry;

/**
 * Help command - displays help for a specific command
 */
class HelpCommand extends Command
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'help';
	}
	
	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Display help for a command';
	}
	
	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addArgument( 'command', false, 'The command to show help for' );
	}
	
	/**
	 * @inheritDoc
	 */
	public function execute(): int
	{
		$commandName = $this->input->getArgument( 'command' );
		
		// If no command specified, show general help
		if( !$commandName )
		{
			$this->showGeneralHelp();
			return 0;
		}
		
		// Get the application from registry
		$app = Registry::getInstance()->get( 'cli.application' );
		
		if( !$app )
		{
			$this->output->error( "Application not found in registry" );
			return 1;
		}
		
		// Check if command exists
		if( !$app->has( $commandName ) )
		{
			$this->output->error( "Command '{$commandName}' not found" );
			$this->output->info( "Run 'neuron list' to see available commands" );
			return 1;
		}
		
		// Get and instantiate the command
		$registry = $app->getRegistry();
		$commandClass = $registry->get( $commandName );
		
		if( !class_exists( $commandClass ) )
		{
			$this->output->error( "Command class not found: {$commandClass}" );
			return 1;
		}
		
		try
		{
			/** @var Command $command */
			$command = new $commandClass();
			$command->configure();
			
			// Display command help
			$this->output->title( "Help: {$commandName}" );
			$this->output->write( $command->getHelp() );
			
			return 0;
		}
		catch( \Exception $e )
		{
			$this->output->error( "Error loading command: " . $e->getMessage() );
			return 1;
		}
	}
	
	/**
	 * Show general help information
	 * 
	 * @return void
	 */
	private function showGeneralHelp(): void
	{
		$this->output->title( "Neuron CLI Help" );
		
		$this->output->info( "Usage:" );
		$this->output->write( "  neuron <command> [options] [arguments]" );
		$this->output->write( "" );
		
		$this->output->info( "Available Commands:" );
		$this->output->write( "  list                List all available commands" );
		$this->output->write( "  help <command>      Display help for a specific command" );
		$this->output->write( "  version             Display version information" );
		$this->output->write( "" );
		
		$this->output->info( "Options:" );
		$this->output->write( "  --help, -h          Display help information" );
		$this->output->write( "  --version, -V       Display version information" );
		$this->output->write( "  --verbose, -v       Increase verbosity of output" );
		$this->output->write( "  --quiet, -q         Suppress all output" );
		$this->output->write( "" );
		
		$this->output->info( "Examples:" );
		$this->output->write( "  neuron list" );
		$this->output->write( "    List all available commands" );
		$this->output->write( "" );
		$this->output->write( "  neuron help cms:init" );
		$this->output->write( "    Show help for the cms:init command" );
		$this->output->write( "" );
		$this->output->write( "  neuron cms:init --theme=blog" );
		$this->output->write( "    Initialize CMS with the blog theme" );
	}
}