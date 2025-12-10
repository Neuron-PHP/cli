<?php

namespace Tests\Cli\Commands\Core;

use Neuron\Cli\Commands\Core\ComponentListCommand;
use Neuron\Cli\Commands\Registry;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use PHPUnit\Framework\TestCase;

class ComponentListCommandTest extends TestCase
{
	private ComponentListCommand $command;
	private Output $output;
	private Registry $registry;

	protected function setUp(): void
	{
		$this->command = new ComponentListCommand();
		$this->command->configure();
		$this->output = new Output( false );
		$this->command->setOutput( $this->output );
		$this->registry = new Registry();
	}

	protected function tearDown(): void
	{
		// Clean up global registry
		\Neuron\Patterns\Registry::getInstance()->reset();
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'list', $this->command->getName() );
	}

	public function testGetDescription(): void
	{
		$this->assertEquals( 'List all available commands', $this->command->getDescription() );
	}

	public function testConfigure(): void
	{
		$options = $this->command->getOptions();

		$this->assertArrayHasKey( 'namespace', $options );
		$this->assertEquals( 'n', $options['namespace']['shortcut'] );
		$this->assertTrue( $options['namespace']['hasValue'] );

		$this->assertArrayHasKey( 'raw', $options );
		$this->assertEquals( 'r', $options['raw']['shortcut'] );
		$this->assertFalse( $options['raw']['hasValue'] );
	}

	public function testExecuteWithNoApplication(): void
	{
		$input = new Input( [] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 1, $exitCode );
		$this->assertStringContainsString( 'Application not found in registry', $output );
	}

	public function testExecuteFormattedList(): void
	{
		// Set up mock application with registry
		$app = $this->createMockApplication();
		$this->registry->register( 'test:command', 'Tests\Cli\Commands\Core\MockTestCommand' );
		$this->registry->register( 'help', 'Neuron\Cli\Commands\Core\HelpCommand' );

		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', $app );

		$input = new Input( [] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 0, $exitCode );
		$this->assertStringContainsString( 'Neuron CLI Commands', $output );
		$this->assertStringContainsString( 'test:command', $output );
		$this->assertStringContainsString( 'help', $output );
	}

	public function testExecuteRawList(): void
	{
		$app = $this->createMockApplication();
		$this->registry->register( 'test:command', 'Tests\Cli\Commands\Core\MockTestCommand' );
		$this->registry->register( 'help', 'Neuron\Cli\Commands\Core\HelpCommand' );

		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', $app );

		$input = new Input( ['--raw'] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 0, $exitCode );
		// Raw output should contain command names without formatting
		$this->assertStringContainsString( 'test:command', $output );
		$this->assertStringContainsString( 'help', $output );
		// Should not contain formatted headers
		$this->assertStringNotContainsString( 'Neuron CLI Commands', $output );
	}

	public function testExecuteWithNamespaceFilter(): void
	{
		$app = $this->createMockApplication();
		$this->registry->register( 'test:command', 'Tests\Cli\Commands\Core\MockTestCommand' );
		$this->registry->register( 'help', 'Neuron\Cli\Commands\Core\HelpCommand' );
		$this->registry->register( 'other:command', 'Tests\Cli\Commands\Core\MockTestCommand' );

		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', $app );

		$input = new Input( ['--namespace=test'] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 0, $exitCode );
		$this->assertStringContainsString( 'test:command', $output );
		$this->assertStringNotContainsString( 'other:command', $output );
		// Global commands (like 'help') should not appear when filtering by namespace
		$this->assertStringNotContainsString( 'Global Commands', $output );
	}

	public function testExecuteWithEmptyRegistry(): void
	{
		$app = $this->createMockApplication();
		// Don't register any commands

		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', $app );

		$input = new Input( [] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 0, $exitCode );
		$this->assertStringContainsString( 'No commands available', $output );
	}

	public function testExecuteWithNamespaceFilterNoMatches(): void
	{
		$app = $this->createMockApplication();
		$this->registry->register( 'test:command', 'Tests\Cli\Commands\Core\MockTestCommand' );

		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', $app );

		$input = new Input( ['--namespace=nonexistent'] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 0, $exitCode );
		$this->assertStringContainsString( "No commands found", $output );
		$this->assertStringContainsString( "namespace 'nonexistent'", $output );
	}

	public function testRawListWithNamespaceFilter(): void
	{
		$app = $this->createMockApplication();
		$this->registry->register( 'test:command1', 'Tests\Cli\Commands\Core\MockTestCommand' );
		$this->registry->register( 'test:command2', 'Tests\Cli\Commands\Core\MockTestCommand' );
		$this->registry->register( 'other:command', 'Tests\Cli\Commands\Core\MockTestCommand' );

		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', $app );

		$input = new Input( ['--raw', '--namespace=test'] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 0, $exitCode );
		$this->assertStringContainsString( 'test:command1', $output );
		$this->assertStringContainsString( 'test:command2', $output );
		$this->assertStringNotContainsString( 'other:command', $output );
	}

	public function testFormattedListGroupsAndSortsNamespaces(): void
	{
		$app = $this->createMockApplication();
		$this->registry->register( 'help', 'Neuron\Cli\Commands\Core\HelpCommand' );
		$this->registry->register( 'zebra:test', 'Tests\Cli\Commands\Core\MockTestCommand' );
		$this->registry->register( 'alpha:test', 'Tests\Cli\Commands\Core\MockTestCommand' );

		\Neuron\Patterns\Registry::getInstance()->set( 'cli.application', $app );

		$input = new Input( [] );
		$input->parse( $this->command );
		$this->command->setInput( $input );

		ob_start();
		$exitCode = $this->command->execute();
		$output = ob_get_clean();

		$this->assertEquals( 0, $exitCode );
		// Should contain all namespaces
		$this->assertStringContainsString( 'Global Commands', $output );
		$this->assertStringContainsString( 'Alpha Commands', $output );
		$this->assertStringContainsString( 'Zebra Commands', $output );

		// Global should appear first (before alpha alphabetically)
		$globalPos = strpos( $output, 'Global Commands' );
		$alphaPos = strpos( $output, 'Alpha Commands' );
		$this->assertLessThan( $alphaPos, $globalPos );
	}

	/**
	 * Create a mock application object
	 */
	private function createMockApplication(): object
	{
		return new class( $this->registry ) {
			private Registry $registry;

			public function __construct( Registry $registry )
			{
				$this->registry = $registry;
			}

			public function getRegistry(): Registry
			{
				return $this->registry;
			}
		};
	}
}

/**
 * Mock command for testing
 */
class MockTestCommand extends \Neuron\Cli\Commands\Command
{
	public function getName(): string
	{
		return 'mock';
	}

	public function getDescription(): string
	{
		return 'Mock command for testing';
	}

	public function configure(): void
	{
		// No configuration needed
	}

	public function execute(): int
	{
		return 0;
	}
}
