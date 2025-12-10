<?php

namespace Tests\Cli\Commands;

use Neuron\Cli\Commands\Command;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use PHPUnit\Framework\TestCase;

class CommandTest extends TestCase
{
	private TestCommand $command;
	
	protected function setUp(): void
	{
		$this->command = new TestCommand();
	}
	
	public function testAddArgument(): void
	{
		$this->command->configure();
		
		$arguments = $this->command->getArguments();
		
		$this->assertArrayHasKey( 'name', $arguments );
		$this->assertTrue( $arguments['name']['required'] );
		$this->assertEquals( 'The name argument', $arguments['name']['description'] );
		
		$this->assertArrayHasKey( 'optional', $arguments );
		$this->assertFalse( $arguments['optional']['required'] );
		$this->assertEquals( 'default', $arguments['optional']['default'] );
	}
	
	public function testAddOption(): void
	{
		$this->command->configure();
		
		$options = $this->command->getOptions();
		
		$this->assertArrayHasKey( 'verbose', $options );
		$this->assertFalse( $options['verbose']['default'] );
		$this->assertEquals( 'Verbose output', $options['verbose']['description'] );
		
		$this->assertArrayHasKey( 'output', $options );
		$this->assertEquals( 'text', $options['output']['default'] );
		
		// Check shortcuts
		$this->assertArrayHasKey( '_shortcuts', $options );
		$this->assertEquals( 'verbose', $options['_shortcuts']['v'] );
		$this->assertEquals( 'output', $options['_shortcuts']['o'] );
	}
	
	public function testGetHelp(): void
	{
		$this->command->configure();
		
		$help = $this->command->getHelp();
		
		// Check that help contains command info
		$this->assertStringContainsString( 'test:command', $help );
		$this->assertStringContainsString( 'Test command description', $help );
		
		// Check arguments section
		$this->assertStringContainsString( 'Arguments:', $help );
		$this->assertStringContainsString( 'name', $help );
		$this->assertStringContainsString( 'The name argument', $help );
		$this->assertStringContainsString( '<name>', $help ); // Required shown in usage
		$this->assertStringContainsString( 'optional', $help );
		$this->assertStringContainsString( '[optional]', $help ); // Optional shown in usage
		$this->assertStringContainsString( '[default: default]', $help );
		
		// Check options section
		$this->assertStringContainsString( 'Options:', $help );
		$this->assertStringContainsString( '-v, --verbose', $help );
		$this->assertStringContainsString( 'Verbose output', $help );
		$this->assertStringContainsString( '-o, --output', $help );
		$this->assertStringContainsString( 'Output format', $help );
		$this->assertStringContainsString( '[default: text]', $help );
	}
	
	public function testSetInputOutput(): void
	{
		$input = new Input( [] );
		$output = new Output( false );
		
		$this->command->setInput( $input );
		$this->command->setOutput( $output );
		
		// Use reflection to check protected properties
		$reflection = new \ReflectionClass( $this->command );
		
		$inputProp = $reflection->getProperty( 'input' );
		$inputProp->setAccessible( true );
		$this->assertSame( $input, $inputProp->getValue( $this->command ) );
		
		$outputProp = $reflection->getProperty( 'output' );
		$outputProp->setAccessible( true );
		$this->assertSame( $output, $outputProp->getValue( $this->command ) );
	}
	
	public function testExecuteReturnsExitCode(): void
	{
		$this->command->setInput( new Input( [] ) );
		$this->command->setOutput( new Output( false ) );

		$exitCode = $this->command->execute();

		$this->assertEquals( 0, $exitCode );
	}

	public function testGetHelpWithOptionWithoutShortcut(): void
	{
		$command = new class extends Command {
			public function getName(): string { return 'test'; }
			public function getDescription(): string { return 'Test'; }
			public function execute(): int { return 0; }
			public function configure(): void {
				$this->addOption( 'long-only', null, true, 'Option without shortcut', 'default' );
			}
		};

		$command->configure();
		$help = $command->getHelp();

		// Should not have shortcut prefix
		$this->assertStringContainsString( '--long-only', $help );
		$this->assertStringContainsString( 'Option without shortcut', $help );
		$this->assertStringContainsString( '[default: default]', $help );
	}

	public function testHasArgument(): void
	{
		$this->command->configure();

		$this->assertTrue( $this->command->hasArgument( 'name' ) );
		$this->assertTrue( $this->command->hasArgument( 'optional' ) );
		$this->assertFalse( $this->command->hasArgument( 'nonexistent' ) );
	}

	public function testHasOption(): void
	{
		$this->command->configure();

		$this->assertTrue( $this->command->hasOption( 'verbose' ) );
		$this->assertTrue( $this->command->hasOption( 'output' ) );
		$this->assertFalse( $this->command->hasOption( 'nonexistent' ) );
	}

	public function testValidateWithMissingRequiredArgument(): void
	{
		$this->command->configure();
		$this->command->setInput( new Input( [] ) ); // Empty input, missing required 'name'

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( "Required argument 'name' is missing" );

		$this->command->validate();
	}

	public function testValidateWithAllRequiredArgumentsPresent(): void
	{
		$this->command->configure();
		$input = new Input( [ 'test-value' ] ); // Pass value as positional argument
		$input->parse( $this->command ); // Parse according to command config
		$this->command->setInput( $input );

		// Should not throw exception
		$this->command->validate();
		$this->assertTrue( true );
	}
}

/**
 * Test implementation of Command for testing purposes
 */
class TestCommand extends Command
{
	public function getName(): string
	{
		return 'test:command';
	}
	
	public function getDescription(): string
	{
		return 'Test command description';
	}
	
	public function configure(): void
	{
		$this->addArgument( 'name', true, 'The name argument' );
		$this->addArgument( 'optional', false, 'Optional argument', 'default' );
		
		$this->addOption( 'verbose', 'v', false, 'Verbose output', false );
		$this->addOption( 'output', 'o', true, 'Output format', 'text' );
	}
	
	public function execute(): int
	{
		return 0;
	}
}