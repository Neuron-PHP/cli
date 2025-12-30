<?php

namespace Neuron\Cli\IO;

/**
 * Interface for reading user input in CLI commands.
 *
 * Provides an abstraction over STDIN to enable testable CLI commands.
 * Implementations can read from actual user input (StdinInputReader)
 * or from pre-programmed responses (TestInputReader) for testing.
 *
 * @package Neuron\Cli\IO
 */
interface IInputReader
{
	/**
	 * Prompt user for input and return their response.
	 *
	 * @param string $message The prompt message to display
	 * @return string The user's response (trimmed)
	 */
	public function prompt( string $message ): string;

	/**
	 * Ask user for yes/no confirmation.
	 *
	 * Accepts: y, yes, true, 1 (case-insensitive) as positive responses.
	 * All other inputs are treated as negative responses.
	 *
	 * @param string $message The confirmation message
	 * @param bool $default Default value if user just presses enter
	 * @return bool True if user confirms, false otherwise
	 */
	public function confirm( string $message, bool $default = false ): bool;

	/**
	 * Prompt for sensitive input without echoing to console.
	 *
	 * Note: Secret input is only supported on Unix-like systems.
	 * On Windows, input will be visible.
	 *
	 * @param string $message The prompt message
	 * @return string The user's input (trimmed)
	 */
	public function secret( string $message ): string;

	/**
	 * Prompt user to select from a list of options.
	 *
	 * Users can select by entering either the option index (numeric)
	 * or the exact option text.
	 *
	 * @param string $message The prompt message
	 * @param array<string> $options Available options
	 * @param string|null $default Default option (will be marked with *)
	 * @return string The selected option
	 */
	public function choice( string $message, array $options, ?string $default = null ): string;
}
