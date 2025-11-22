<?php

namespace Neuron\Cli\Commands\Generate;

use Neuron\Cli\Commands\Command;

/**
 * CLI command for generating initializer classes.
 */
class InitializerCommand extends Command
{
	private string $_ProjectPath;
	private string $_StubPath;

	public function __construct()
	{
		$this->_ProjectPath = getcwd();
		$this->_StubPath = __DIR__ . '/stubs';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'initializer:generate';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Generate an initializer class';
	}

	/**
	 * Configure the command
	 */
	public function configure(): void
	{
		$this->addArgument( 'name', true, 'Initializer name (e.g., QueueInitializer)' );
		$this->addOption( 'namespace', null, true, 'Initializer namespace', 'App\\Initializers' );
		$this->addOption( 'force', 'f', false, 'Overwrite existing files' );
	}

	/**
	 * Execute the command
	 */
	public function execute(): int
	{
		$this->output->info( "\n╔═══════════════════════════════════════╗" );
		$this->output->info( "║  Initializer Generator                ║" );
		$this->output->info( "╚═══════════════════════════════════════╝\n" );

		// Get initializer name
		$initializerName = $this->input->getArgument( 'name' );
		if( empty( $initializerName ) )
		{
			$this->output->error( 'Initializer name is required' );
			return 1;
		}

		// Get namespace
		$namespace = $this->input->getOption( 'namespace', 'App\\Initializers' );
		$namespace = rtrim( $namespace, '\\' );

		// Generate initializer
		if( !$this->generateInitializer( $initializerName, $namespace ) )
		{
			return 1;
		}

		$this->output->newLine();
		$this->output->success( 'Initializer generated successfully!' );
		$this->output->info( "\nThe initializer will be automatically loaded by InitializerRunner on application start." );
		$this->output->info( "Implement your initialization logic in the run() method." );

		return 0;
	}

	/**
	 * Generate initializer class
	 */
	private function generateInitializer( string $initializerName, string $namespace ): bool
	{
		// Determine file path
		$namespacePath = str_replace( '\\', '/', str_replace( 'App\\', '', $namespace ) );
		$initializerDir = $this->_ProjectPath . '/app/' . $namespacePath;
		$initializerFile = $initializerDir . '/' . $initializerName . '.php';

		// Check if exists
		if( file_exists( $initializerFile ) && !$this->input->hasOption( 'force' ) )
		{
			$this->output->error( "Initializer already exists: {$initializerFile}" );
			$this->output->info( 'Use --force to overwrite' );
			return false;
		}

		// Create directory
		if( !is_dir( $initializerDir ) )
		{
			if( !mkdir( $initializerDir, 0755, true ) )
			{
				$this->output->error( "Could not create directory: {$initializerDir}" );
				return false;
			}
		}

		// Load stub
		$stub = $this->loadStub( 'initializer.stub' );
		if( !$stub )
		{
			$this->output->error( 'Could not load initializer stub' );
			return false;
		}

		// Replace placeholders
		$content = str_replace(
			[ '{{namespace}}', '{{class}}' ],
			[ $namespace, $initializerName ],
			$stub
		);

		// Write file
		if( file_put_contents( $initializerFile, $content ) === false )
		{
			$this->output->error( 'Could not write initializer file' );
			return false;
		}

		$this->output->success( "Created initializer: {$initializerFile}" );
		return true;
	}

	/**
	 * Load a stub file
	 */
	private function loadStub( string $name ): ?string
	{
		$stubFile = $this->_StubPath . '/' . $name;

		if( !file_exists( $stubFile ) )
		{
			return null;
		}

		return file_get_contents( $stubFile );
	}
}
