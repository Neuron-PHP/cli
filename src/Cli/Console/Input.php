<?php

namespace Neuron\Cli\Console;

use Neuron\Cli\Commands\Command;

/**
 * Handles input parsing and retrieval for CLI commands.
 * Processes command-line arguments and options.
 */
class Input
{
	private array $argv;
	private array $arguments = [];
	private array $options = [];
	private array $rawArguments = [];
	
	/**
	 * @param array $argv Command-line arguments (without script name)
	 */
	public function __construct( array $argv = [] )
	{
		$this->argv = $argv;
		$this->parseRaw();
	}
	
	/**
	 * Parse raw input into arguments and options
	 * 
	 * @return void
	 */
	private function parseRaw(): void
	{
		foreach( $this->argv as $arg )
		{
			// Long option with value (--option=value)
			if( preg_match( '/^--([^=]+)=(.*)$/', $arg, $matches ) )
			{
				$this->options[$matches[1]] = $matches[2];
			}
			// Long option without value (--option)
			elseif( preg_match( '/^--(.+)$/', $arg, $matches ) )
			{
				$this->options[$matches[1]] = true;
			}
			// Short options (-v or -vvv or -abc)
			elseif( preg_match( '/^-([^-].*)$/', $arg, $matches ) )
			{
				// Handle multiple short options like -abc as -a -b -c
				$shorts = str_split( $matches[1] );
				foreach( $shorts as $short )
				{
					// Count occurrences for verbosity levels (-vvv)
					if( isset( $this->options[$short] ) && is_int( $this->options[$short] ) )
					{
						$this->options[$short]++;
					}
					elseif( isset( $this->options[$short] ) && $this->options[$short] === true )
					{
						$this->options[$short] = 2; // Was 1 (true), now 2
					}
					else
					{
						$this->options[$short] = true;
					}
				}
			}
			// Regular argument
			else
			{
				$this->rawArguments[] = $arg;
			}
		}
	}
	
	/**
	 * Parse input according to command configuration
	 * 
	 * @param Command $command
	 * @return void
	 */
	public function parse( Command $command ): void
	{
		$commandArgs = $command->getArguments();
		$commandOpts = $command->getOptions();
		
		// Map raw arguments to named arguments based on position
		$position = 0;
		foreach( $commandArgs as $name => $config )
		{
			if( isset( $this->rawArguments[$position] ) )
			{
				$this->arguments[$name] = $this->rawArguments[$position];
				$position++;
			}
			elseif( $config['default'] !== null )
			{
				$this->arguments[$name] = $config['default'];
			}
		}
		
		// Process option shortcuts
		if( isset( $commandOpts['_shortcuts'] ) )
		{
			foreach( $commandOpts['_shortcuts'] as $short => $long )
			{
				if( isset( $this->options[$short] ) )
				{
					// Map short option to long form
					$this->options[$long] = $this->options[$short];
				}
			}
		}
		
		// Apply default values for missing options
		foreach( $commandOpts as $name => $config )
		{
			if( $name === '_shortcuts' )
			{
				continue;
			}
			
			if( !isset( $this->options[$name] ) && $config['default'] !== null )
			{
				$this->options[$name] = $config['default'];
			}
		}
	}
	
	/**
	 * Get an argument value
	 * 
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public function getArgument( string $name, mixed $default = null ): mixed
	{
		return $this->arguments[$name] ?? $default;
	}
	
	/**
	 * Get all arguments
	 * 
	 * @return array
	 */
	public function getArguments(): array
	{
		return $this->arguments;
	}
	
	/**
	 * Check if an argument exists
	 * 
	 * @param string $name
	 * @return bool
	 */
	public function hasArgument( string $name ): bool
	{
		return isset( $this->arguments[$name] );
	}
	
	/**
	 * Get an option value
	 * 
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public function getOption( string $name, mixed $default = null ): mixed
	{
		return $this->options[$name] ?? $default;
	}
	
	/**
	 * Get all options
	 * 
	 * @return array
	 */
	public function getOptions(): array
	{
		return $this->options;
	}
	
	/**
	 * Check if an option exists
	 * 
	 * @param string $name
	 * @return bool
	 */
	public function hasOption( string $name ): bool
	{
		return isset( $this->options[$name] );
	}
	
	/**
	 * Set an argument value
	 * 
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public function setArgument( string $name, mixed $value ): void
	{
		$this->arguments[$name] = $value;
	}
	
	/**
	 * Set an option value
	 * 
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public function setOption( string $name, mixed $value ): void
	{
		$this->options[$name] = $value;
	}
	
	/**
	 * Get the raw arguments array
	 * 
	 * @return array
	 */
	public function getRawArguments(): array
	{
		return $this->rawArguments;
	}
	
	/**
	 * Get the original argv array
	 * 
	 * @return array
	 */
	public function getArgv(): array
	{
		return $this->argv;
	}
	
	/**
	 * Check if input is interactive (connected to a TTY)
	 * 
	 * @return bool
	 */
	public function isInteractive(): bool
	{
		return posix_isatty( STDIN );
	}
	
	/**
	 * Read a line from standard input
	 * 
	 * @param string $prompt Optional prompt to display
	 * @return string|false
	 */
	public function readLine( string $prompt = '' ): string|false
	{
		if( $prompt )
		{
			echo $prompt;
		}
		
		return fgets( STDIN );
	}
	
	/**
	 * Ask a question and return the answer
	 * 
	 * @param string $question
	 * @param string|null $default
	 * @return string
	 */
	public function ask( string $question, ?string $default = null ): string
	{
		$prompt = $question;
		
		if( $default !== null )
		{
			$prompt .= " [{$default}]";
		}
		
		$prompt .= ': ';
		
		$answer = trim( $this->readLine( $prompt ) ?: '' );
		
		if( empty( $answer ) && $default !== null )
		{
			return $default;
		}
		
		return $answer;
	}
	
	/**
	 * Ask a yes/no question
	 * 
	 * @param string $question
	 * @param bool $default
	 * @return bool
	 */
	public function confirm( string $question, bool $default = false ): bool
	{
		$defaultStr = $default ? 'Y/n' : 'y/N';
		$prompt = "{$question} [{$defaultStr}]: ";
		
		$answer = strtolower( trim( $this->readLine( $prompt ) ?: '' ) );
		
		if( empty( $answer ) )
		{
			return $default;
		}
		
		return in_array( $answer, ['y', 'yes', '1', 'true'] );
	}
	
	/**
	 * Ask for secret input (e.g., passwords) with hidden characters
	 * 
	 * @param string $question
	 * @return string
	 */
	public function askSecret( string $question ): string
	{
		$prompt = $question . ': ';
		
		// Check if we're on Windows
		if( DIRECTORY_SEPARATOR === '\\' )
		{
			// Windows approach using PowerShell
			echo $prompt;
			$command = 'powershell -Command "$p = Read-Host -AsSecureString; $p = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p)); echo $p"';
			$password = rtrim( shell_exec( $command ) );
			echo PHP_EOL;
			return $password ?: '';
		}
		else
		{
			// Unix/Linux/Mac approach using stty
			echo $prompt;
			
			// Save current stty settings
			$oldStty = shell_exec( 'stty -g' );
			
			// Disable echo
			system( 'stty -echo', $result );
			
			// Read the password
			$password = fgets( STDIN );
			
			// Restore stty settings
			system( "stty {$oldStty}" );
			
			// Add newline since echo was disabled
			echo PHP_EOL;
			
			return $password ? trim( $password ) : '';
		}
	}
	
	/**
	 * Ask the user to choose from a list of options
	 * 
	 * @param string $question
	 * @param array $choices Array of choices (can be associative)
	 * @param mixed $default Default choice (key or value)
	 * @param bool $allowMultiple Allow multiple selections
	 * @return string|array Single choice or array of choices if multiple allowed
	 */
	public function choice( string $question, array $choices, mixed $default = null, bool $allowMultiple = false ): string|array
	{
		// Normalize choices to ensure we have both keys and values
		$normalizedChoices = [];
		$isAssoc = array_keys( $choices ) !== range( 0, count( $choices ) - 1 );
		
		if( $isAssoc )
		{
			$normalizedChoices = $choices;
		}
		else
		{
			// Convert to associative array with values as keys
			foreach( $choices as $choice )
			{
				$normalizedChoices[$choice] = $choice;
			}
		}
		
		// Display the question
		echo $question . PHP_EOL;
		
		// Display choices
		$index = 1;
		$indexMap = [];
		foreach( $normalizedChoices as $key => $value )
		{
			$indexMap[$index] = $key;
			$isDefault = ( $default !== null && ( $key === $default || $value === $default ) );
			$marker = $isDefault ? ' (default)' : '';
			echo "  [{$index}] {$value}{$marker}" . PHP_EOL;
			$index++;
		}
		
		// Build prompt
		if( $allowMultiple )
		{
			$prompt = 'Enter your choices (comma-separated numbers or values)';
		}
		else
		{
			$prompt = 'Enter your choice (number or value)';
		}
		
		if( $default !== null )
		{
			$prompt .= " or press Enter for default";
		}
		$prompt .= ': ';
		
		// Get user input
		$input = trim( $this->readLine( $prompt ) ?: '' );
		
		// Handle default
		if( empty( $input ) && $default !== null )
		{
			return $default;
		}
		
		// Parse input
		if( $allowMultiple )
		{
			$selections = array_map( 'trim', explode( ',', $input ) );
			$results = [];
			
			foreach( $selections as $selection )
			{
				$choice = $this->parseChoice( $selection, $normalizedChoices, $indexMap );
				if( $choice !== null )
				{
					$results[] = $choice;
				}
			}
			
			return array_unique( $results );
		}
		else
		{
			$choice = $this->parseChoice( $input, $normalizedChoices, $indexMap );
			if( $choice === null && $default === null )
			{
				// Invalid choice and no default, ask again
				echo "Invalid choice. Please try again." . PHP_EOL;
				return $this->choice( $question, $choices, $default, $allowMultiple );
			}
			
			return $choice ?? $default;
		}
	}
	
	/**
	 * Parse a single choice input
	 * 
	 * @param string $input
	 * @param array $choices
	 * @param array $indexMap
	 * @return string|null
	 */
	private function parseChoice( string $input, array $choices, array $indexMap ): ?string
	{
		// Check if input is a number (index)
		if( is_numeric( $input ) )
		{
			$index = (int) $input;
			if( isset( $indexMap[$index] ) )
			{
				return $indexMap[$index];
			}
		}
		
		// Check if input matches a key
		if( isset( $choices[$input] ) )
		{
			return $input;
		}
		
		// Check if input matches a value (case-insensitive)
		foreach( $choices as $key => $value )
		{
			if( strcasecmp( $value, $input ) === 0 )
			{
				return $key;
			}
		}
		
		return null;
	}
}