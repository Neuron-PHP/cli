<?php

namespace Neuron\Cli\IO;

use Neuron\Cli\Console\Output;

/**
 * Production input reader that reads from STDIN.
 *
 * This is the default input reader used by CLI commands in production.
 * It reads actual user input from the standard input stream.
 *
 * @package Neuron\Cli\IO
 */
class StdinInputReader implements IInputReader
{
	/**
	 * @param Output $output Output instance for displaying prompts
	 */
	public function __construct(
		private Output $output
	) {}

	/**
	 * @inheritDoc
	 */
	public function prompt( string $message ): string
	{
		$this->output->write( $message, false );
		$input = fgets( STDIN );
		return $input !== false ? trim( $input ) : '';
	}

	/**
	 * @inheritDoc
	 */
	public function confirm( string $message, bool $default = false ): bool
	{
		$suffix = $default ? ' [Y/n]: ' : ' [y/N]: ';
		$response = $this->prompt( $message . $suffix );

		// If user just presses enter, use default
		if( empty( $response ) ) {
			return $default;
		}

		// Accept y, yes, true, 1 as positive (case-insensitive)
		return in_array( strtolower( $response ), ['y', 'yes', 'true', '1'], true );
	}

	/**
	 * @inheritDoc
	 */
	public function secret( string $message ): string
	{
		$this->output->write( $message, false );

		// Only hide input on Unix-like systems
		if( strtoupper( substr( PHP_OS, 0, 3 ) ) !== 'WIN' ) {
			// Disable terminal echo
			system( 'stty -echo' );
			$input = fgets( STDIN );
			// Re-enable terminal echo
			system( 'stty echo' );
			// Add newline since user's enter key wasn't echoed
			$this->output->writeln( '' );
		} else {
			// On Windows, fall back to visible input
			// A proper Windows implementation would use COM or other Windows-specific methods
			$input = fgets( STDIN );
		}

		return $input !== false ? trim( $input ) : '';
	}

	/**
	 * @inheritDoc
	 */
	public function choice( string $message, array $options, ?string $default = null ): string
	{
		// Display the prompt message
		$this->output->writeln( $message );
		$this->output->writeln( '' );

		// Display options with index numbers
		foreach( $options as $index => $option ) {
			$marker = ($default === $option) ? '*' : ' ';
			$this->output->writeln( "  [{$marker}] {$index}. {$option}" );
		}

		$this->output->writeln( '' );

		// Prompt for selection
		$prompt = $default !== null ? "Choice [{$default}]: " : "Choice: ";
		$response = $this->prompt( $prompt );

		// If user just presses enter and there's a default, use it
		if( empty( $response ) && $default !== null ) {
			return $default;
		}

		// Check if response is a numeric index
		if( is_numeric( $response ) ) {
			$index = (int)$response;
			if( isset( $options[$index] ) ) {
				return $options[$index];
			}
		}

		// Check if response matches an option exactly
		if( in_array( $response, $options, true ) ) {
			return $response;
		}

		// Invalid choice - ask again
		$this->output->error( "Invalid choice. Please try again." );
		$this->output->writeln( '' );

		return $this->choice( $message, $options, $default );
	}
}
