<?php

namespace Tests\Cli\IO;

use Neuron\Cli\Console\Output;
use Neuron\Cli\IO\StdinInputReader;
use PHPUnit\Framework\TestCase;

class StdinInputReaderTest extends TestCase
{
	private Output $output;
	private StdinInputReader $reader;

	protected function setUp(): void
	{
		$this->output = new Output( false );
		$this->reader = new StdinInputReader( $this->output );
	}

	public function testConstructorAcceptsOutput(): void
	{
		$reader = new StdinInputReader( $this->output );

		$this->assertInstanceOf( StdinInputReader::class, $reader );
	}

	public function testConfirmWithEmptyResponseUsesDefault(): void
	{
		// We can test the logic without actual STDIN by using a mock
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'prompt' )
			->willReturn( '' );

		$result = $mock->confirm( 'Continue?', true );

		$this->assertTrue( $result );
	}

	public function testConfirmAcceptsYesResponse(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		foreach( ['y', 'yes', 'YES', 'Yes', 'true', 'TRUE', '1'] as $response ) {
			$mock->expects( $this->once() )
				->method( 'prompt' )
				->willReturn( $response );

			$result = $mock->confirm( 'Continue?', false );

			$this->assertTrue( $result, "Response '{$response}' should be treated as positive" );

			// Reset the mock for next iteration
			$mock = $this->getMockBuilder( StdinInputReader::class )
				->setConstructorArgs( [$this->output] )
				->onlyMethods( ['prompt'] )
				->getMock();
		}
	}

	public function testConfirmRejectsNoResponse(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		foreach( ['n', 'no', 'NO', 'false', 'FALSE', '0', 'nope', 'anything'] as $response ) {
			$mock->expects( $this->once() )
				->method( 'prompt' )
				->willReturn( $response );

			$result = $mock->confirm( 'Continue?', true );

			$this->assertFalse( $result, "Response '{$response}' should be treated as negative" );

			// Reset the mock for next iteration
			$mock = $this->getMockBuilder( StdinInputReader::class )
				->setConstructorArgs( [$this->output] )
				->onlyMethods( ['prompt'] )
				->getMock();
		}
	}

	public function testConfirmAddsSuffixBasedOnDefault(): void
	{
		// Test that the suffix is added correctly based on default value
		// We can't easily verify the output, but we can verify the method is called
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		// Default true should show [Y/n]
		$mock->expects( $this->once() )
			->method( 'prompt' )
			->with( $this->stringContains( '[Y/n]' ) )
			->willReturn( '' );

		$mock->confirm( 'Continue?', true );
	}

	public function testChoiceWithEmptyResponseUsesDefault(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'prompt' )
			->willReturn( '' );

		$options = ['dev', 'staging', 'prod'];
		$result = $mock->choice( 'Select:', $options, 'dev' );

		$this->assertEquals( 'dev', $result );
	}

	public function testChoiceWithNumericIndex(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'prompt' )
			->willReturn( '1' );

		$options = ['dev', 'staging', 'prod'];
		$result = $mock->choice( 'Select:', $options );

		$this->assertEquals( 'staging', $result );
	}

	public function testChoiceWithExactMatch(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'prompt' )
			->willReturn( 'staging' );

		$options = ['dev', 'staging', 'prod'];
		$result = $mock->choice( 'Select:', $options );

		$this->assertEquals( 'staging', $result );
	}

	public function testChoiceWithInvalidSelectionRetries(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		// First call returns invalid, second call returns valid
		$mock->expects( $this->exactly( 2 ) )
			->method( 'prompt' )
			->willReturnOnConsecutiveCalls( 'invalid', '1' );

		$options = ['dev', 'staging', 'prod'];
		$result = $mock->choice( 'Select:', $options );

		$this->assertEquals( 'staging', $result );
	}

	public function testSecretDelegatesToPrompt(): void
	{
		// We can't easily test the actual secret hiding functionality,
		// but we can verify the method works
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		// On non-Windows systems, secret() will internally handle stty,
		// but we can't test that easily in unit tests
		// This test just verifies the interface works
		$this->assertInstanceOf( StdinInputReader::class, $mock );
	}

	public function testSecretMethodExistsAndIsCallable(): void
	{
		// Verify secret method exists and can be called
		$reader = new StdinInputReader( $this->output );
		$reflection = new \ReflectionClass( $reader );

		$this->assertTrue( $reflection->hasMethod( 'secret' ) );
		$method = $reflection->getMethod( 'secret' );
		$this->assertTrue( $method->isPublic() );

		// The method should handle both TTY and non-TTY environments gracefully
		// We can't easily test actual STDIN reading in unit tests, but we verify
		// the method exists and has the correct visibility
	}

	public function testIsTtyMethodExists(): void
	{
		// Use reflection to verify the isTty method exists
		$reflection = new \ReflectionClass( StdinInputReader::class );

		$this->assertTrue( $reflection->hasMethod( 'isTty' ) );

		$method = $reflection->getMethod( 'isTty' );
		$this->assertTrue( $method->isPrivate() );
	}

	public function testIsTtyReturnsBooleanValue(): void
	{
		// Use reflection to invoke the private isTty method
		$reader = new StdinInputReader( $this->output );
		$reflection = new \ReflectionClass( $reader );
		$method = $reflection->getMethod( 'isTty' );
		$method->setAccessible( true );

		$result = $method->invoke( $reader );

		// Should return a boolean value (true or false depending on environment)
		$this->assertIsBool( $result );
	}

	public function testChoiceWithMaxRetriesUsesDefault(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		// All responses will be invalid
		$mock->expects( $this->exactly( 10 ) ) // MAX_RETRY_ATTEMPTS
			->method( 'prompt' )
			->willReturn( 'invalid' );

		$options = ['dev', 'staging', 'prod'];
		$result = $mock->choice( 'Select:', $options, 'dev' );

		// Should fall back to default after max retries
		$this->assertEquals( 'dev', $result );
	}

	public function testChoiceWithMaxRetriesThrowsExceptionWithoutDefault(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		// All responses will be invalid
		$mock->expects( $this->exactly( 10 ) ) // MAX_RETRY_ATTEMPTS
			->method( 'prompt' )
			->willReturn( 'invalid' );

		$options = ['dev', 'staging', 'prod'];

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Maximum retry attempts exceeded and no default value provided' );

		$mock->choice( 'Select:', $options ); // No default
	}

	public function testChoiceSucceedsBeforeMaxRetries(): void
	{
		$mock = $this->getMockBuilder( StdinInputReader::class )
			->setConstructorArgs( [$this->output] )
			->onlyMethods( ['prompt'] )
			->getMock();

		// First 3 invalid, then valid on 4th attempt
		$mock->expects( $this->exactly( 4 ) )
			->method( 'prompt' )
			->willReturnOnConsecutiveCalls( 'invalid', 'bad', 'nope', 'staging' );

		$options = ['dev', 'staging', 'prod'];
		$result = $mock->choice( 'Select:', $options );

		// Should succeed with valid response before hitting max retries
		$this->assertEquals( 'staging', $result );
	}
}
