<?php

namespace Neuron\Cli\Commands;

use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cli\IO\IInputReader;
use Neuron\Cli\IO\StdinInputReader;

/**
 * Abstract base class for all CLI commands.
 * Provides common functionality for command configuration,
 * input/output handling, and execution.
 */
abstract class Command
{
	protected Input $input;
	protected Output $output;
	protected ?IInputReader $inputReader = null;
	protected array $arguments = [];
	protected array $options = [];
	
	/**
	 * Get the command name
	 * 
	 * @return string
	 */
	abstract public function getName(): string;
	
	/**
	 * Get the command description
	 * 
	 * @return string
	 */
	abstract public function getDescription(): string;
	
	/**
	 * Execute the command
	 * 
	 * @return int Exit code (0 for success)
	 */
	abstract public function execute(): int;
	
	/**
	 * Configure the command (add arguments and options)
	 * Override this method to define command parameters
	 * 
	 * @return void
	 */
	public function configure(): void
	{
		// Override in subclasses to add arguments and options
	}
	
	/**
	 * Set the input instance
	 * 
	 * @param Input $input
	 * @return self
	 */
	public function setInput( Input $input ): self
	{
		$this->input = $input;
		return $this;
	}
	
	/**
	 * Set the output instance
	 * 
	 * @param Output $output
	 * @return self
	 */
	public function setOutput( Output $output ): self
	{
		$this->output = $output;
		return $this;
	}
	
	/**
	 * Add an argument to the command
	 * 
	 * @param string $name Argument name
	 * @param bool $required Whether the argument is required
	 * @param string $description Description of the argument
	 * @param mixed $default Default value if not provided
	 * @return self
	 */
	protected function addArgument( 
		string $name, 
		bool $required = false, 
		string $description = '', 
		mixed $default = null 
	): self
	{
		$this->arguments[$name] = [
			'required' => $required,
			'description' => $description,
			'default' => $default,
			'position' => count( $this->arguments )
		];
		
		return $this;
	}
	
	/**
	 * Add an option to the command
	 * 
	 * @param string $name Option name (long form)
	 * @param string|null $shortcut Option shortcut (single character)
	 * @param bool $hasValue Whether the option accepts a value
	 * @param string $description Description of the option
	 * @param mixed $default Default value if not provided
	 * @return self
	 */
	protected function addOption( 
		string $name, 
		?string $shortcut = null, 
		bool $hasValue = false,
		string $description = '',
		mixed $default = null
	): self
	{
		$this->options[$name] = [
			'shortcut' => $shortcut,
			'hasValue' => $hasValue,
			'description' => $description,
			'default' => $default
		];
		
		// Register shortcut mapping
		if( $shortcut !== null )
		{
			$this->options['_shortcuts'][$shortcut] = $name;
		}
		
		return $this;
	}
	
	/**
	 * Get all configured arguments
	 * 
	 * @return array
	 */
	public function getArguments(): array
	{
		return $this->arguments;
	}
	
	/**
	 * Get all configured options
	 * 
	 * @return array
	 */
	public function getOptions(): array
	{
		return $this->options;
	}
	
	/**
	 * Get the help text for this command
	 * 
	 * @return string
	 */
	public function getHelp(): string
	{
		$help = [];
		
		// Command name and description
		$help[] = "Description:";
		$help[] = "  " . $this->getDescription();
		$help[] = "";
		
		// Usage
		$help[] = "Usage:";
		$usage = "  neuron " . $this->getName();
		
		// Add options to usage
		if( !empty( $this->options ) )
		{
			$usage .= " [options]";
		}
		
		// Add arguments to usage
		foreach( $this->arguments as $name => $config )
		{
			if( $config['required'] )
			{
				$usage .= " <{$name}>";
			}
			else
			{
				$usage .= " [{$name}]";
			}
		}
		
		$help[] = $usage;
		$help[] = "";
		
		// Arguments
		if( !empty( $this->arguments ) )
		{
			$help[] = "Arguments:";
			
			foreach( $this->arguments as $name => $config )
			{
				$line = "  " . str_pad( $name, 20 );
				$line .= $config['description'];
				
				if( !$config['required'] && $config['default'] !== null )
				{
					$line .= " [default: {$config['default']}]";
				}
				
				$help[] = $line;
			}
			
			$help[] = "";
		}
		
		// Options
		if( !empty( $this->options ) )
		{
			$help[] = "Options:";
			
			foreach( $this->options as $name => $config )
			{
				// Skip internal shortcuts mapping
				if( $name === '_shortcuts' )
				{
					continue;
				}
				
				$optionStr = "  ";
				
				// Add shortcut if available
				if( $config['shortcut'] !== null )
				{
					$optionStr .= "-{$config['shortcut']}, ";
				}
				else
				{
					$optionStr .= "    ";
				}
				
				// Add long form
				$optionStr .= "--{$name}";
				
				// Add value placeholder if option takes a value
				if( $config['hasValue'] )
				{
					$optionStr .= "=VALUE";
				}
				
				$line = str_pad( $optionStr, 30 );
				$line .= $config['description'];
				
				if( $config['default'] !== null )
				{
					$line .= " [default: {$config['default']}]";
				}
				
				$help[] = $line;
			}
		}
		
		return implode( "\n", $help );
	}
	
	/**
	 * Check if the command has a specific argument
	 * 
	 * @param string $name
	 * @return bool
	 */
	public function hasArgument( string $name ): bool
	{
		return isset( $this->arguments[$name] );
	}
	
	/**
	 * Check if the command has a specific option
	 * 
	 * @param string $name
	 * @return bool
	 */
	public function hasOption( string $name ): bool
	{
		return isset( $this->options[$name] );
	}
	
	/**
	 * Validate that required arguments are present
	 *
	 * @throws \InvalidArgumentException
	 * @return void
	 */
	public function validate(): void
	{
		foreach( $this->arguments as $name => $config )
		{
			if( $config['required'] && !$this->input->hasArgument( $name ) )
			{
				throw new \InvalidArgumentException( "Required argument '{$name}' is missing" );
			}
		}
	}

	/**
	 * Get the input reader instance.
	 *
	 * Creates a default StdinInputReader if not already set.
	 * This enables testable CLI commands by abstracting user input.
	 *
	 * @return IInputReader
	 */
	protected function getInputReader(): IInputReader
	{
		if( !$this->inputReader ) {
			$this->inputReader = new StdinInputReader( $this->output );
		}

		return $this->inputReader;
	}

	/**
	 * Set the input reader (for dependency injection, especially in tests).
	 *
	 * This allows tests to inject a TestInputReader with pre-programmed
	 * responses instead of requiring actual user input.
	 *
	 * @param IInputReader $inputReader
	 * @return self
	 */
	public function setInputReader( IInputReader $inputReader ): self
	{
		$this->inputReader = $inputReader;
		return $this;
	}

	/**
	 * Prompt user for input.
	 *
	 * Convenience method that delegates to the input reader.
	 *
	 * Example:
	 * ```php
	 * $name = $this->prompt( "Enter your name: " );
	 * ```
	 *
	 * @param string $message The prompt message to display
	 * @return string The user's response (trimmed)
	 */
	protected function prompt( string $message ): string
	{
		return $this->getInputReader()->prompt( $message );
	}

	/**
	 * Ask user for yes/no confirmation.
	 *
	 * Convenience method that delegates to the input reader.
	 * Accepts: y, yes, true, 1 (case-insensitive) as positive responses.
	 *
	 * Example:
	 * ```php
	 * if( $this->confirm( "Delete all files?" ) ) {
	 *     // User confirmed
	 * }
	 * ```
	 *
	 * @param string $message The confirmation message
	 * @param bool $default Default value if user just presses enter
	 * @return bool True if user confirms, false otherwise
	 */
	protected function confirm( string $message, bool $default = false ): bool
	{
		return $this->getInputReader()->confirm( $message, $default );
	}

	/**
	 * Prompt for sensitive input without echoing to console.
	 *
	 * Convenience method that delegates to the input reader.
	 * Note: Secret input hiding only works on Unix-like systems.
	 *
	 * Example:
	 * ```php
	 * $password = $this->secret( "Enter password: " );
	 * ```
	 *
	 * @param string $message The prompt message
	 * @return string The user's input (trimmed)
	 */
	protected function secret( string $message ): string
	{
		return $this->getInputReader()->secret( $message );
	}

	/**
	 * Prompt user to select from a list of options.
	 *
	 * Convenience method that delegates to the input reader.
	 * Users can select by entering either the option index (numeric)
	 * or the exact option text.
	 *
	 * Example:
	 * ```php
	 * $env = $this->choice(
	 *     "Select environment:",
	 *     ['development', 'staging', 'production'],
	 *     'development'
	 * );
	 * ```
	 *
	 * @param string $message The prompt message
	 * @param array<string> $options Available options
	 * @param string|null $default Default option (will be marked with *)
	 * @return string The selected option
	 */
	protected function choice( string $message, array $options, ?string $default = null ): string
	{
		return $this->getInputReader()->choice( $message, $options, $default );
	}
}