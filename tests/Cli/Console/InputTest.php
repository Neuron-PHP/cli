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
}