<?php

namespace Neuron\Cli\Commands;

use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;

/**
 * Abstract base class for all CLI commands.
 * Provides common functionality for command configuration,
 * input/output handling, and execution.
 */
abstract class Command
{
	protected Input $input;
	protected Output $output;
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
}