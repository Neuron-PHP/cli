<?php

namespace Tests\Cli\Commands\Core;

use Neuron\Cli\Commands\Core\HelpCommand;
use Neuron\Cli\Commands\Registry;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use PHPUnit\Framework\TestCase;

class HelpCommandTest extends TestCase
{
	private HelpCommand $command;
	private Output $output;

	protected function setUp(): void
	{
		$this->command = new HelpCommand();
		$this->command->configure();
		$this->output = new Output( false );
		$this->command->setOutput( $this->output );
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'help', $this->command->getName() );
	}

	public function testGetDescription(): void
	{
		$this->assertEquals( 'Display help for a command', $this->command->getDescription() );
	}

	public function testConfigure(): void
	{
		$arguments = $this->command->getArguments();

		$this->assertArrayHasKey( 'command', $arguments );
		$this->assertFalse( $arguments['command']['required'] );
		$this->assertEquals( 'The command to show help for', $arguments['command']['description'] );
	}

	public function testExecuteWithoutArgumentShowsGeneralHelp(): void
	{
		$input = new Input( [] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 0, $exitCode );
		$this->assertStringContainsString( 'Neuron CLI Help', $output );
		$this->assertStringContainsString( 'Usage:', $output );
		$this->assertStringContainsString( 'Available Commands:', $output );
		$this->assertStringContainsString( 'Options:', $output );
		$this->assertStringContainsString( 'Examples:', $output );
	}

	public function testGeneralHelpContainsCommonCommands(): void
	{
		$input = new Input( [] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$this->command->execute();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'list', $output );
		$this->assertStringContainsString( 'help <command>', $output );
		$this->assertStringContainsString( 'version', $output );
	}

	public function testGeneralHelpContainsExamples(): void
	{
		$input = new Input( [] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$this->command->execute();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'neuron list', $output );
		$this->assertStringContainsString( 'neuron help cms:init', $output );
		$this->assertStringContainsString( 'neuron cms:init --theme=blog', $output );
	}

	public function testExecuteWithoutApplicationInRegistry(): void
	{
		// Clear registry
		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', null );

		$input = new Input( ['test:command'] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 1, $exitCode );
		$this->assertStringContainsString( 'Application not found in registry', $output );
	}

	public function testExecuteWithNonExistentCommand(): void
	{
		// Create mock application
		$mockApp = $this->createMock( \Neuron\Cli\Application::class );
		$mockApp->method( 'has' )->willReturn( false );

		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', $mockApp );

		$input = new Input( ['nonexistent:command'] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 1, $exitCode );
		$this->assertStringContainsString( "Command 'nonexistent:command' not found", $output );
		$this->assertStringContainsString( "Run 'neuron list' to see available commands", $output );

		// Cleanup
		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', null );
	}

	public function testExecuteWithNonExistentCommandClass(): void
	{
		// Create mock registry
		$mockRegistry = $this->createMock( Registry::class );
		$mockRegistry->method( 'get' )->willReturn( 'NonExistentCommandClass' );

		// Create mock application
		$mockApp = $this->createMock( \Neuron\Cli\Application::class );
		$mockApp->method( 'has' )->willReturn( true );
		$mockApp->method( 'getRegistry' )->willReturn( $mockRegistry );

		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', $mockApp );

		$input = new Input( ['test:command'] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 1, $exitCode );
		$this->assertStringContainsString( 'Command class not found', $output );

		// Cleanup
		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', null );
	}

	protected function tearDown(): void
	{
		// Cleanup registry after each test
		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', null );
	}
}
