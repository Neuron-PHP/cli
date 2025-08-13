<?php

namespace Tests\Cli\Console;

use Neuron\Cli\Console\Output;
use PHPUnit\Framework\TestCase;

class OutputTest extends TestCase
{
	private Output $output;
	
	protected function setUp(): void
	{
		// Create output without colors for testing
		$this->output = new Output( false );
	}
	
	public function testVerbosityLevels(): void
	{
		$this->assertEquals( 1, $this->output->getVerbosity() );
		
		$this->output->setVerbosity( 3 );
		
		$this->assertEquals( 3, $this->output->getVerbosity() );
	}
	
	public function testWriteCapture(): void
	{
		// Capture output
		ob_start();
		$this->output->write( 'Test message' );
		$captured = ob_get_clean();
		
		$this->assertEquals( "Test message" . PHP_EOL, $captured );
	}
	
	public function testWriteWithoutNewline(): void
	{
		ob_start();
		$this->output->write( 'Test', false );
		$captured = ob_get_clean();
		
		$this->assertEquals( 'Test', $captured );
	}
	
	public function testInfoMessage(): void
	{
		ob_start();
		$this->output->info( 'Info message' );
		$captured = ob_get_clean();
		
		// Without colors, should just be the message
		$this->assertEquals( "Info message" . PHP_EOL, $captured );
	}
	
	public function testSuccessMessage(): void
	{
		ob_start();
		$this->output->success( 'Success' );
		$captured = ob_get_clean();
		
		$this->assertStringContainsString( '✓ Success', $captured );
	}
	
	public function testWarningMessage(): void
	{
		ob_start();
		$this->output->warning( 'Warning' );
		$captured = ob_get_clean();
		
		$this->assertStringContainsString( '⚠ Warning', $captured );
	}
	
	public function testErrorMessage(): void
	{
		ob_start();
		$this->output->error( 'Error' );
		$captured = ob_get_clean();
		
		$this->assertStringContainsString( '✗ Error', $captured );
	}
	
	public function testNewLine(): void
	{
		ob_start();
		$this->output->newLine( 3 );
		$captured = ob_get_clean();
		
		$this->assertEquals( PHP_EOL . PHP_EOL . PHP_EOL, $captured );
	}
	
	public function testDebugWithVerbosity(): void
	{
		// Default verbosity is 1, debug requires 2
		ob_start();
		$this->output->debug( 'Debug message' );
		$captured = ob_get_clean();
		
		$this->assertEquals( '', $captured );
		
		// Increase verbosity
		$this->output->setVerbosity( 2 );
		
		ob_start();
		$this->output->debug( 'Debug message' );
		$captured = ob_get_clean();
		
		$this->assertStringContainsString( '[DEBUG] Debug message', $captured );
	}
	
	public function testTableOutput(): void
	{
		ob_start();
		$this->output->table( 
			['Name', 'Version'],
			[
				['cli', '1.0.0'],
				['core', '0.7.0']
			]
		);
		$captured = ob_get_clean();
		
		// Check that headers are present
		$this->assertStringContainsString( 'Name', $captured );
		$this->assertStringContainsString( 'Version', $captured );
		
		// Check that data is present
		$this->assertStringContainsString( 'cli', $captured );
		$this->assertStringContainsString( '1.0.0', $captured );
		$this->assertStringContainsString( 'core', $captured );
		$this->assertStringContainsString( '0.7.0', $captured );
	}
	
	public function testSection(): void
	{
		ob_start();
		$this->output->section( 'Test Section' );
		$captured = ob_get_clean();
		
		$lines = explode( PHP_EOL, $captured );
		
		// Should have blank line, title, separator, blank line
		$this->assertCount( 5, $lines ); // 4 lines + empty string at end
		$this->assertEquals( '', $lines[0] );
		$this->assertEquals( 'Test Section', $lines[1] );
		$this->assertEquals( '============', $lines[2] );
		$this->assertEquals( '', $lines[3] );
	}
	
	public function testTitle(): void
	{
		ob_start();
		$this->output->title( 'Test' );
		$captured = ob_get_clean();
		
		// Should have the title with spaces
		$this->assertStringContainsString( '  Test  ', $captured );
		// Should have newlines before and after
		$this->assertStringStartsWith( PHP_EOL, $captured );
		$this->assertStringEndsWith( PHP_EOL . PHP_EOL, $captured );
	}
}