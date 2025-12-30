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
	 * Maximum number of retry attempts for invalid input.
	 * After this many failed attempts, the method will fall back to the default
	 * or throw an exception if no default is provided.
	 */
	private const MAX_RETRY_ATTEMPTS = 10;

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
		if( $response === '' ) {
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

		// Check if we can hide input (Unix-like system + TTY)
		$canHideInput = strtoupper( substr( PHP_OS, 0, 3 ) ) !== 'WIN' && $this->isTty();

		if( $canHideInput ) {
			// Disable terminal echo with exception safety
			try {
				system( 'stty -echo' );
				$input = fgets( STDIN );
			} finally {
				// Always restore terminal echo, even if an exception occurred
				system( 'stty echo' );
			}

			// Add newline since user's enter key wasn't echoed
			$this->output->writeln( '' );
		} else {
			// Fall back to visible input (Windows or non-TTY)
			// On Windows, a proper implementation would use COM or other Windows-specific methods
			// For non-TTY (CI/CD, piped input), we just read normally without trying stty
			$input = fgets( STDIN );
		}

		return $input !== false ? trim( $input ) : '';
	}

	/**
	 * Check if STDIN is a TTY (terminal).
	 *
	 * This prevents stty errors when running in non-interactive environments
	 * like CI/CD pipelines, automated scripts, or with piped input.
	 *
	 * @return bool True if STDIN is a TTY, false otherwise
	 */
	private function isTty(): bool
	{
		// Use posix_isatty if available (preferred, more reliable)
		if( function_exists( 'posix_isatty' ) ) {
			return @posix_isatty( STDIN );
		}

		// Fall back to stream_isatty (PHP 7.2+)
		if( function_exists( 'stream_isatty' ) ) {
			return @stream_isatty( STDIN );
		}

		// If neither function is available, assume it's not a TTY to be safe
		// This prevents errors in environments where we can't check
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function choice( string $message, array $options, ?string $default = null ): string
	{
		$attempts = 0;

		while( $attempts < self::MAX_RETRY_ATTEMPTS ) {
			// Display the prompt message (only on first attempt)
			if( $attempts === 0 ) {
				$this->output->writeln( $message );
				$this->output->writeln( '' );

				// Display options with index numbers
				foreach( $options as $index => $option ) {
					$marker = ($default === $option) ? '*' : ' ';
					$this->output->writeln( "  [{$marker}] {$index}. {$option}" );
				}

				$this->output->writeln( '' );
			}

			// Prompt for selection
			$prompt = $default !== null ? "Choice [{$default}]: " : "Choice: ";
			$response = $this->prompt( $prompt );

			// If user just presses enter and there's a default, use it
			if( $response === '' && $default !== null ) {
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

			// Invalid choice - increment counter and show error
			$attempts++;

			if( $attempts < self::MAX_RETRY_ATTEMPTS ) {
				$this->output->error( "Invalid choice. Please try again." );
				$this->output->writeln( '' );
			}
		}

		// Max retries exceeded - fall back to default or throw exception
		if( $default !== null ) {
			$this->output->warning( "Maximum retry attempts exceeded. Using default: {$default}" );
			return $default;
		}

		throw new \RuntimeException(
			'Maximum retry attempts exceeded and no default value provided for choice prompt'
		);
	}
}
