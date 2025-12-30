<?php

namespace Neuron\Cli\Console;

/**
 * Interface for input/output stream abstraction.
 *
 * Provides a testable abstraction over STDIN/STDOUT for interactive CLI operations.
 * Implementations can be real streams for production or mock streams for testing.
 */
interface StreamInterface
{
	/**
	 * Read a line from the input stream
	 *
	 * @return string|false Returns the read line or false on EOF
	 */
	public function read(): string|false;

	/**
	 * Write data to the output stream
	 *
	 * @param string $data Data to write
	 * @return void
	 */
	public function write( string $data ): void;

	/**
	 * Check if the stream is connected to an interactive terminal
	 *
	 * @return bool True if interactive (TTY), false otherwise
	 */
	public function isInteractive(): bool;
}
