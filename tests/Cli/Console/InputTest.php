<?php

namespace Tests\Cli\Console;

use Neuron\Cli\Console\Input;
use PHPUnit\Framework\TestCase;

class InputTest extends TestCase
{
	public function testParseArguments(): void
	{
		$input = new Input( ['arg1', 'arg2', 'arg3'] );
		
		$rawArgs = $input->getRawArguments();
		
		$this->assertCount( 3, $rawArgs );
		$this->assertEquals( 'arg1', $rawArgs[0] );
		$this->assertEquals( 'arg2', $rawArgs[1] );
		$this->assertEquals( 'arg3', $rawArgs[2] );
	}
	
	public function testParseLongOptions(): void
	{
		$input = new Input( ['--verbose', '--output=json', '--no-cache'] );
		
		$options = $input->getOptions();
		
		$this->assertTrue( $options['verbose'] );
		$this->assertEquals( 'json', $options['output'] );
		$this->assertTrue( $options['no-cache'] );
	}
	
	public function testParseShortOptions(): void
	{
		$input = new Input( ['-v', '-h', '-abc'] );
		
		$options = $input->getOptions();
		
		$this->assertTrue( $options['v'] );
		$this->assertTrue( $options['h'] );
		$this->assertTrue( $options['a'] );
		$this->assertTrue( $options['b'] );
		$this->assertTrue( $options['c'] );
	}
	
	public function testParseVerbosityLevels(): void
	{
		$input = new Input( ['-vvv'] );
		
		$options = $input->getOptions();
		
		// First 'v' sets to true (1), second to 2, third to 3
		$this->assertEquals( 3, $options['v'] );
	}
	
	public function testMixedArgumentsAndOptions(): void
	{
		$input = new Input( ['command', '--option=value', 'argument', '-v'] );
		
		$rawArgs = $input->getRawArguments();
		$options = $input->getOptions();
		
		$this->assertCount( 2, $rawArgs );
		$this->assertEquals( 'command', $rawArgs[0] );
		$this->assertEquals( 'argument', $rawArgs[1] );
		
		$this->assertEquals( 'value', $options['option'] );
		$this->assertTrue( $options['v'] );
	}
	
	public function testHasOption(): void
	{
		$input = new Input( ['--verbose', '--output=json'] );
		
		$this->assertTrue( $input->hasOption( 'verbose' ) );
		$this->assertTrue( $input->hasOption( 'output' ) );
		$this->assertFalse( $input->hasOption( 'missing' ) );
	}
	
	public function testGetOption(): void
	{
		$input = new Input( ['--output=json'] );
		
		$this->assertEquals( 'json', $input->getOption( 'output' ) );
		$this->assertEquals( 'default', $input->getOption( 'missing', 'default' ) );
		$this->assertNull( $input->getOption( 'missing' ) );
	}
	
	public function testSetArgument(): void
	{
		$input = new Input( [] );
		
		$input->setArgument( 'test', 'value' );
		
		$this->assertEquals( 'value', $input->getArgument( 'test' ) );
		$this->assertTrue( $input->hasArgument( 'test' ) );
	}
	
	public function testSetOption(): void
	{
		$input = new Input( [] );
		
		$input->setOption( 'test', 'value' );
		
		$this->assertEquals( 'value', $input->getOption( 'test' ) );
		$this->assertTrue( $input->hasOption( 'test' ) );
	}
	
	public function testEmptyInput(): void
	{
		$input = new Input( [] );

		$this->assertEmpty( $input->getRawArguments() );
		$this->assertEmpty( $input->getOptions() );
		$this->assertEmpty( $input->getArguments() );
	}

	public function testParseWithCommand(): void
	{
		// Create a mock command with arguments and options
		$command = $this->createMock( \Neuron\Cli\Commands\Command::class );

		$command->method( 'getArguments' )->willReturn([
			'name' => ['required' => true, 'description' => 'Name', 'default' => null],
			'optional' => ['required' => false, 'description' => 'Optional', 'default' => 'default_value']
		]);

		$command->method( 'getOptions' )->willReturn([
			'verbose' => ['shortcut' => 'v', 'hasValue' => false, 'description' => 'Verbose', 'default' => false],
			'output' => ['shortcut' => 'o', 'hasValue' => true, 'description' => 'Output', 'default' => 'text'],
			'_shortcuts' => ['v' => 'verbose', 'o' => 'output']
		]);

		$input = new Input( ['test', '--verbose', '--output=json'] );
		$input->parse( $command );

		// Check arguments were mapped
		$this->assertEquals( 'test', $input->getArgument( 'name' ) );
		$this->assertEquals( 'default_value', $input->getArgument( 'optional' ) );

		// Check option shortcuts were processed
		$this->assertTrue( $input->getOption( 'verbose' ) );
		$this->assertEquals( 'json', $input->getOption( 'output' ) );
	}

	public function testParseAppliesDefaultValues(): void
	{
		$command = $this->createMock( \Neuron\Cli\Commands\Command::class );

		$command->method( 'getArguments' )->willReturn([
			'arg' => ['required' => false, 'description' => 'Arg', 'default' => 'arg_default']
		]);

		$command->method( 'getOptions' )->willReturn([
			'option' => ['shortcut' => null, 'hasValue' => true, 'description' => 'Option', 'default' => 'opt_default']
		]);

		$input = new Input( [] );
		$input->parse( $command );

		// Defaults should be applied
		$this->assertEquals( 'arg_default', $input->getArgument( 'arg' ) );
		$this->assertEquals( 'opt_default', $input->getOption( 'option' ) );
	}

	public function testGetArguments(): void
	{
		$input = new Input( ['arg1', 'arg2'] );
		$input->setArgument( 'first', 'value1' );
		$input->setArgument( 'second', 'value2' );

		$arguments = $input->getArguments();

		$this->assertCount( 2, $arguments );
		$this->assertEquals( 'value1', $arguments['first'] );
		$this->assertEquals( 'value2', $arguments['second'] );
	}

	public function testGetArgument(): void
	{
		$input = new Input( [] );
		$input->setArgument( 'test', 'value' );

		$this->assertEquals( 'value', $input->getArgument( 'test' ) );
		$this->assertEquals( 'default', $input->getArgument( 'missing', 'default' ) );
		$this->assertNull( $input->getArgument( 'missing' ) );
	}

	public function testHasArgument(): void
	{
		$input = new Input( [] );
		$input->setArgument( 'exists', 'value' );

		$this->assertTrue( $input->hasArgument( 'exists' ) );
		$this->assertFalse( $input->hasArgument( 'missing' ) );
	}

	public function testGetArgv(): void
	{
		$argv = ['command', '--option', 'value'];
		$input = new Input( $argv );

		$this->assertEquals( $argv, $input->getArgv() );
	}

	public function testVerbosityLevelSeparate(): void
	{
		// Test multiple -v flags as separate arguments
		$input = new Input( ['-v', '-v', '-v'] );

		$options = $input->getOptions();

		// Each -v should increment the count
		$this->assertEquals( 3, $options['v'] );
	}

	public function testShortOptionWithoutHyphen(): void
	{
		// Test that arguments starting with - but followed by another - are handled
		$input = new Input( ['--long-option', 'regular-arg'] );

		$rawArgs = $input->getRawArguments();
		$options = $input->getOptions();

		// 'regular-arg' should be treated as a regular argument, not an option
		$this->assertCount( 1, $rawArgs );
		$this->assertEquals( 'regular-arg', $rawArgs[0] );
		$this->assertTrue( $options['long-option'] );
	}

	public function testParsePositionalArguments(): void
	{
		$command = $this->createMock( \Neuron\Cli\Commands\Command::class );

		$command->method( 'getArguments' )->willReturn([
			'first' => ['required' => true, 'description' => 'First', 'default' => null],
			'second' => ['required' => true, 'description' => 'Second', 'default' => null],
			'third' => ['required' => false, 'description' => 'Third', 'default' => 'third_default']
		]);

		$command->method( 'getOptions' )->willReturn([]);

		$input = new Input( ['value1', 'value2'] );
		$input->parse( $command );

		// First two arguments should be mapped by position
		$this->assertEquals( 'value1', $input->getArgument( 'first' ) );
		$this->assertEquals( 'value2', $input->getArgument( 'second' ) );
		// Third should get default value
		$this->assertEquals( 'third_default', $input->getArgument( 'third' ) );
	}

	public function testOptionWithoutShortcut(): void
	{
		$command = $this->createMock( \Neuron\Cli\Commands\Command::class );

		$command->method( 'getArguments' )->willReturn([]);

		$command->method( 'getOptions' )->willReturn([
			'long-only' => ['shortcut' => null, 'hasValue' => true, 'description' => 'Long only', 'default' => 'default']
		]);

		$input = new Input( ['--long-only=custom'] );
		$input->parse( $command );

		$this->assertEquals( 'custom', $input->getOption( 'long-only' ) );
	}

	// Interactive method tests using TestStream

	public function testAskWithAnswer(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( ['John Doe'] );

		$input = new Input( [], $stream );
		$result = $input->ask( 'What is your name?' );

		$this->assertEquals( 'John Doe', $result );

		$outputs = $stream->getOutputs();
		$this->assertCount( 1, $outputs );
		$this->assertStringContainsString( 'What is your name?: ', $outputs[0] );
	}

	public function testAskWithDefaultAndEmptyAnswer(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( [''] );

		$input = new Input( [], $stream );
		$result = $input->ask( 'What is your name?', 'Anonymous' );

		$this->assertEquals( 'Anonymous', $result );

		$outputs = $stream->getOutputs();
		$this->assertStringContainsString( '[Anonymous]', $outputs[0] );
	}

	public function testAskWithDefaultAndProvidedAnswer(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( ['Jane'] );

		$input = new Input( [], $stream );
		$result = $input->ask( 'What is your name?', 'Anonymous' );

		$this->assertEquals( 'Jane', $result );
	}

	public function testConfirmWithYes(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( ['yes'] );

		$input = new Input( [], $stream );
		$result = $input->confirm( 'Continue?' );

		$this->assertTrue( $result );

		$outputs = $stream->getOutputs();
		$this->assertStringContainsString( '[y/N]', $outputs[0] );
	}

	public function testConfirmWithY(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( ['y'] );

		$input = new Input( [], $stream );
		$result = $input->confirm( 'Continue?' );

		$this->assertTrue( $result );
	}

	public function testConfirmWithNo(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( ['no'] );

		$input = new Input( [], $stream );
		$result = $input->confirm( 'Continue?' );

		$this->assertFalse( $result );
	}

	public function testConfirmWithDefaultTrue(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( [''] );

		$input = new Input( [], $stream );
		$result = $input->confirm( 'Continue?', true );

		$this->assertTrue( $result );

		$outputs = $stream->getOutputs();
		$this->assertStringContainsString( '[Y/n]', $outputs[0] );
	}

	public function testConfirmWithDefaultFalse(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( [''] );

		$input = new Input( [], $stream );
		$result = $input->confirm( 'Continue?', false );

		$this->assertFalse( $result );
	}

	public function testChoiceWithNumericSelection(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( ['2'] );

		$input = new Input( [], $stream );
		$choices = ['red', 'green', 'blue'];
		$result = $input->choice( 'Pick a color:', $choices );

		$this->assertEquals( 'green', $result );

		$output = $stream->getOutput();
		$this->assertStringContainsString( '[1] red', $output );
		$this->assertStringContainsString( '[2] green', $output );
		$this->assertStringContainsString( '[3] blue', $output );
	}

	public function testChoiceWithTextSelection(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( ['blue'] );

		$input = new Input( [], $stream );
		$choices = ['red', 'green', 'blue'];
		$result = $input->choice( 'Pick a color:', $choices );

		$this->assertEquals( 'blue', $result );
	}

	public function testChoiceWithDefault(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( [''] );

		$input = new Input( [], $stream );
		$choices = ['red', 'green', 'blue'];
		$result = $input->choice( 'Pick a color:', $choices, 'green' );

		$this->assertEquals( 'green', $result );

		$output = $stream->getOutput();
		$this->assertStringContainsString( '(default)', $output );
	}

	public function testChoiceWithAssociativeArray(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( ['1'] );

		$input = new Input( [], $stream );
		$choices = ['r' => 'Red', 'g' => 'Green', 'b' => 'Blue'];
		$result = $input->choice( 'Pick a color:', $choices );

		$this->assertEquals( 'r', $result );
	}

	public function testChoiceWithMultipleSelections(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( ['1,3'] );

		$input = new Input( [], $stream );
		$choices = ['red', 'green', 'blue'];
		$result = $input->choice( 'Pick colors:', $choices, null, true );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertContains( 'red', $result );
		$this->assertContains( 'blue', $result );
	}

	public function testChoiceWithInvalidThenValid(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( ['99', '2'] );

		$input = new Input( [], $stream );
		$choices = ['red', 'green', 'blue'];
		$result = $input->choice( 'Pick a color:', $choices );

		$this->assertEquals( 'green', $result );

		$output = $stream->getOutput();
		$this->assertStringContainsString( 'Invalid choice', $output );
	}

	public function testReadLine(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInputs( ['test input'] );

		$input = new Input( [], $stream );
		$result = $input->readLine( 'Enter text: ' );

		$this->assertEquals( 'test input', $result );

		$outputs = $stream->getOutputs();
		$this->assertEquals( 'Enter text: ', $outputs[0] );
	}

	public function testIsInteractive(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInteractive( true );

		$input = new Input( [], $stream );

		$this->assertTrue( $input->isInteractive() );
	}

	public function testIsNotInteractive(): void
	{
		$stream = new \Neuron\Cli\Console\TestStream();
		$stream->setInteractive( false );

		$input = new Input( [], $stream );

		$this->assertFalse( $input->isInteractive() );
	}
}