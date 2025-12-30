<?php

namespace Tests\Cli\Commands;

use Neuron\Cli\Commands\Command;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cli\IO\IInputReader;
use Neuron\Cli\IO\TestInputReader;
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

	public function testSetInputReader(): void
	{
		$inputReader = new TestInputReader();
		$result = $this->command->setInputReader( $inputReader );

		// Should return self for fluent interface
		$this->assertSame( $this->command, $result );

		// Verify it was set using reflection
		$reflection = new \ReflectionClass( $this->command );
		$prop = $reflection->getProperty( 'inputReader' );
		$prop->setAccessible( true );

		$this->assertSame( $inputReader, $prop->getValue( $this->command ) );
	}

	public function testGetInputReaderCreatesDefaultWhenNotSet(): void
	{
		$this->command->setInput( new Input( [] ) );
		$this->command->setOutput( new Output( false ) );

		// Access protected method using reflection
		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'getInputReader' );
		$method->setAccessible( true );

		$reader = $method->invoke( $this->command );

		$this->assertInstanceOf( IInputReader::class, $reader );
	}

	public function testGetInputReaderCreatesDefaultOutputWhenNotSet(): void
	{
		// Don't set output - should auto-initialize
		// Access protected method using reflection
		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'getInputReader' );
		$method->setAccessible( true );

		// Should not throw exception even though output wasn't set
		$reader = $method->invoke( $this->command );

		$this->assertInstanceOf( IInputReader::class, $reader );

		// Verify output was auto-initialized
		$outputProp = $reflection->getProperty( 'output' );
		$outputProp->setAccessible( true );
		$output = $outputProp->getValue( $this->command );

		$this->assertInstanceOf( Output::class, $output );
	}

	public function testGetInputReaderReturnsInjectedReader(): void
	{
		$inputReader = new TestInputReader();
		$this->command->setInputReader( $inputReader );

		// Access protected method using reflection
		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'getInputReader' );
		$method->setAccessible( true );

		$reader = $method->invoke( $this->command );

		$this->assertSame( $inputReader, $reader );
	}

	public function testPromptDelegatesToInputReader(): void
	{
		$inputReader = new TestInputReader();
		$inputReader->addResponse( 'test response' );

		$command = new InteractiveTestCommand();
		$command->setInput( new Input( [] ) );
		$command->setOutput( new Output( false ) );
		$command->setInputReader( $inputReader );

		$result = $command->callPrompt( 'Enter something: ' );

		$this->assertEquals( 'test response', $result );

		// Verify prompt was shown
		$history = $inputReader->getPromptHistory();
		$this->assertCount( 1, $history );
		$this->assertEquals( 'Enter something: ', $history[0] );
	}

	public function testConfirmDelegatesToInputReader(): void
	{
		$inputReader = new TestInputReader();
		$inputReader->addResponse( 'yes' );

		$command = new InteractiveTestCommand();
		$command->setInput( new Input( [] ) );
		$command->setOutput( new Output( false ) );
		$command->setInputReader( $inputReader );

		$result = $command->callConfirm( 'Are you sure?', false );

		$this->assertTrue( $result );
	}

	public function testConfirmWithDefault(): void
	{
		$inputReader = new TestInputReader();
		$inputReader->addResponse( '' ); // Empty response

		$command = new InteractiveTestCommand();
		$command->setInput( new Input( [] ) );
		$command->setOutput( new Output( false ) );
		$command->setInputReader( $inputReader );

		// Should use default when response is empty
		$result = $command->callConfirm( 'Continue?', true );

		$this->assertTrue( $result );
	}

	public function testSecretDelegatesToInputReader(): void
	{
		$inputReader = new TestInputReader();
		$inputReader->addResponse( 'secret-password' );

		$command = new InteractiveTestCommand();
		$command->setInput( new Input( [] ) );
		$command->setOutput( new Output( false ) );
		$command->setInputReader( $inputReader );

		$result = $command->callSecret( 'Enter password: ' );

		$this->assertEquals( 'secret-password', $result );
	}

	public function testChoiceDelegatesToInputReader(): void
	{
		$inputReader = new TestInputReader();
		$inputReader->addResponse( 'staging' );

		$command = new InteractiveTestCommand();
		$command->setInput( new Input( [] ) );
		$command->setOutput( new Output( false ) );
		$command->setInputReader( $inputReader );

		$options = ['development', 'staging', 'production'];
		$result = $command->callChoice( 'Select environment:', $options );

		$this->assertEquals( 'staging', $result );
	}

	public function testChoiceByIndex(): void
	{
		$inputReader = new TestInputReader();
		$inputReader->addResponse( '1' ); // Select index 1

		$command = new InteractiveTestCommand();
		$command->setInput( new Input( [] ) );
		$command->setOutput( new Output( false ) );
		$command->setInputReader( $inputReader );

		$options = ['dev', 'staging', 'prod'];
		$result = $command->callChoice( 'Select:', $options );

		$this->assertEquals( 'staging', $result );
	}

	public function testChoiceWithDefault(): void
	{
		$inputReader = new TestInputReader();
		$inputReader->addResponse( '' ); // Empty response

		$command = new InteractiveTestCommand();
		$command->setInput( new Input( [] ) );
		$command->setOutput( new Output( false ) );
		$command->setInputReader( $inputReader );

		$options = ['dev', 'staging', 'prod'];
		$result = $command->callChoice( 'Select:', $options, 'dev' );

		$this->assertEquals( 'dev', $result );
	}

	public function testPromptWorksWithoutOutputSet(): void
	{
		// Test that prompt works even if setOutput() was never called
		$inputReader = new TestInputReader();
		$inputReader->addResponse( 'test' );

		$command = new InteractiveTestCommand();
		$command->setInputReader( $inputReader );

		// Should not throw exception even though output wasn't set
		$result = $command->callPrompt( 'Enter: ' );

		$this->assertEquals( 'test', $result );
	}

	public function testConfirmWorksWithoutOutputSet(): void
	{
		// Test that confirm works even if setOutput() was never called
		$inputReader = new TestInputReader();
		$inputReader->addResponse( 'yes' );

		$command = new InteractiveTestCommand();
		$command->setInputReader( $inputReader );

		// Should not throw exception even though output wasn't set
		$result = $command->callConfirm( 'Continue?' );

		$this->assertTrue( $result );
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

/**
 * Test command that exposes protected methods for testing
 */
class InteractiveTestCommand extends Command
{
	public function getName(): string
	{
		return 'interactive:test';
	}

	public function getDescription(): string
	{
		return 'Test command for interactive methods';
	}

	public function execute(): int
	{
		return 0;
	}

	// Expose protected methods for testing
	public function callPrompt( string $message ): string
	{
		return $this->prompt( $message );
	}

	public function callConfirm( string $message, bool $default = false ): bool
	{
		return $this->confirm( $message, $default );
	}

	public function callSecret( string $message ): string
	{
		return $this->secret( $message );
	}

	public function callChoice( string $message, array $options, ?string $default = null ): string
	{
		return $this->choice( $message, $options, $default );
	}
}