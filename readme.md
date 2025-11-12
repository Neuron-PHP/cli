[![CI](https://github.com/Neuron-PHP/cli/actions/workflows/ci.yml/badge.svg)](https://github.com/Neuron-PHP/cli/actions)
# Neuron CLI Component

A unified command-line interface for the Neuron PHP framework that provides a modern, extensible CLI tool for all Neuron components.

## Features

-  **Unified CLI Interface** - Single entry point for all component commands
-  **Automatic Command Discovery** - Auto-detects commands from installed components
-  **Rich Terminal Output** - Colored output, tables, and progress bars
-  **Zero Configuration** - Works out of the box with any Neuron component
-  **Extensible Architecture** - Easy to add custom commands

## Requirements

- PHP 8.4 or higher
- Composer

## Installation

### Local Installation (Recommended)

```bash
composer require neuron-php/cli
```

After installation, the CLI will be available at:
```bash
./vendor/bin/neuron
```

### Global Installation

```bash
composer global require neuron-php/cli
```

Make sure your global Composer bin directory is in your PATH, then:
```bash
neuron version
```

## Basic Usage

### List All Commands

```bash
neuron list
```

### Get Help

```bash
# General help
neuron --help

# Help for specific command
neuron help make:controller
```

## Config Export

Export a YAML config as environment-style pairs for production.

```bash
# Export project config to .env production file
./vendor/bin/neuron config:env --config=config/neuron.yaml > .env.production

# Only export selected categories
./vendor/bin/neuron config:env --category=site,cache

# Shell format (export lines)
./vendor/bin/neuron config:env --format=shell

# Quoting control: auto|always|never (default: auto)
./vendor/bin/neuron config:env --quote=always
```

Notes:
- Keys become UPPER_SNAKE_CASE using `CATEGORY_NAME` from YAML.
- Nested and array values are flattened (e.g., `ARRAY_KEY_0`, `ARRAY_KEY_1`).
- Booleans and nulls are stringified appropriately for env files.

## For Component Developers

The CLI component provides two ways for your component to register commands:

### Method 1: CLI Provider Class (Recommended)

This is the preferred method as it keeps all command registrations in one place.

1. **Update your component's `composer.json`:**

```json
{
    "name": "neuron-php/your-component",
    "extra": {
        "neuron": {
            "cli-provider": "Neuron\\YourComponent\\Cli\\CommandProvider"
        }
    }
}
```

2. **Create the provider class:**

```php
<?php

namespace Neuron\YourComponent\Cli;

class CommandProvider
{
    public static function register($app): void
    {
        // Register your commands
        $app->register('component:command1', Command1::class);
        $app->register('component:command2', Command2::class);
        $app->register('component:command3', Command3::class);
    }
}
```

### Method 2: Direct Registration

For simpler cases, you can register commands directly in `composer.json`:

```json
{
    "name": "neuron-php/your-component",
    "extra": {
        "neuron": {
            "commands": {
                "component:command": "Neuron\\YourComponent\\Commands\\YourCommand",
                "component:another": "Neuron\\YourComponent\\Commands\\AnotherCommand"
            }
        }
    }
}
```

### Creating Command Classes

All commands must extend the base `Command` class:

```php
<?php

namespace Neuron\YourComponent\Commands;

use Neuron\Cli\Commands\Command;

class MakeControllerCommand extends Command
{
    /**
     * Get the command name (how it's invoked)
     */
    public function getName(): string
    {
        return 'make:controller';
    }
    
    /**
     * Get the command description (shown in list)
     */
    public function getDescription(): string
    {
        return 'Create a new controller class';
    }
    
    /**
     * Configure arguments and options
     */
    public function configure(): void
    {
        // Add required argument
        $this->addArgument('name', true, 'The controller name');
        
        // Add optional argument with default
        $this->addArgument('namespace', false, 'Custom namespace', 'App\\Controllers');
        
        // Add boolean option (flag)
        $this->addOption('resource', 'r', false, 'Create a resource controller');
        
        // Add option that accepts a value
        $this->addOption('model', 'm', true, 'Model to bind to controller');
        
        // Add option with default value
        $this->addOption('template', 't', true, 'Template to use', 'default');
    }
    
    /**
     * Execute the command
     * 
     * @return int Exit code (0 for success)
     */
    public function execute(): int
    {
        // Get arguments
        $name = $this->input->getArgument('name');
        $namespace = $this->input->getArgument('namespace');
        
        // Get options
        $isResource = $this->input->getOption('resource');
        $model = $this->input->getOption('model');
        
        // Output messages
        $this->output->info("Creating controller: {$name}");
        
        // Show progress for long operations
        $progress = $this->output->createProgressBar(100);
        $progress->start();
        
        for ($i = 0; $i < 100; $i++) {
            // Do work...
            $progress->advance();
            usleep(10000);
        }
        
        $progress->finish();
        
        // Success message
        $this->output->success("Controller created successfully!");
        
        return 0; // Success
    }
}
```

### Command Naming Conventions

- Use namespace format: `component:action` (e.g., `mvc:controller`, `cms:init`)
- Use kebab-case for multi-word actions: `make:controller`, `cache:clear`
- Group related commands under the same namespace
- Keep names short but descriptive

## Built-in Commands

### Core Commands

| Command | Description | Usage |
|---------|-------------|-------|
| `help` | Display help for a command | `neuron help [command]` |
| `list` | List all available commands | `neuron list` |
| `version` | Show version information | `neuron version [--verbose]` |
| `config:env` | Export neuron.yaml as KEY=VALUE pairs | `neuron config:env [--config=...] [--category=...] [--format=dotenv|shell] [--quote=...]` |

## Output Helpers

The `Output` class provides rich formatting options:

### Basic Output

```php
// Simple messages
$this->output->write('Simple message');
$this->output->writeln('Message with newline', 'green');

// Styled messages
$this->output->info('Information message');      // Cyan
$this->output->success('Success message');       // Green with ✓
$this->output->warning('Warning message');       // Yellow with ⚠
$this->output->error('Error message');          // Red with ✗
$this->output->comment('Comment');              // Yellow

// Sections and titles
$this->output->title('Command Title');
$this->output->section('Section Header');
```

### Tables

```php
$this->output->table(
    ['Name', 'Version', 'Description'],
    [
        ['cli', '1.0.0', 'CLI component'],
        ['mvc', '0.6.0', 'MVC framework'],
        ['cms', '0.5.0', 'Content management'],
    ]
);
```

### Progress Bars

```php
$progress = $this->output->createProgressBar(100);
$progress->start();

foreach ($items as $item) {
    // Process item...
    $progress->advance();
}

$progress->finish();
```

### Interactive Input

The CLI component provides full support for interactive user input in commands:

```php
// Check if terminal is interactive
if (!$this->input->isInteractive()) {
    $this->output->error('This command requires an interactive terminal');
    return 1;
}

// Ask for text input with optional default
$name = $this->input->ask('What is your name?', 'Anonymous');

// Ask without default (returns empty string if no input)
$email = $this->input->ask('Enter your email');

// Ask yes/no questions
if ($this->input->confirm('Do you want to continue?', true)) {
    // User confirmed (pressed y/yes/1/true or Enter with default true)
}

// Read raw input with custom prompt
$line = $this->input->readLine('> ');
```

#### Interactive Input Methods

| Method | Description | Example |
|--------|-------------|---------|
| `isInteractive()` | Check if terminal supports interaction | `if ($this->input->isInteractive())` |
| `ask($question, $default)` | Ask for text input | `$name = $this->input->ask('Name?', 'John')` |
| `confirm($question, $default)` | Ask yes/no question | `if ($this->input->confirm('Continue?', true))` |
| `askSecret($question)` | Ask for hidden input (passwords) | `$pass = $this->input->askSecret('Password')` |
| `choice($question, $choices, $default, $multiple)` | Select from options | `$opt = $this->input->choice('Pick:', ['A', 'B'])` |
| `readLine($prompt)` | Read raw input line | `$line = $this->input->readLine('> ')` |

#### Example: Interactive Setup Command

```php
class SetupCommand extends Command
{
    public function execute(): int
    {
        if (!$this->input->isInteractive()) {
            $this->output->error('Setup requires interactive mode');
            return 1;
        }
        
        // Gather information
        $project = $this->input->ask('Project name', 'my-app');
        $author = $this->input->ask('Author name');
        $email = $this->input->ask('Author email');
        
        // Validate email
        while (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->output->error('Invalid email format');
            $email = $this->input->ask('Author email');
        }
        
        // Show summary
        $this->output->section('Configuration Summary');
        $this->output->write("Project: {$project}");
        $this->output->write("Author: {$author} <{$email}>");
        
        // Confirm
        if (!$this->input->confirm('Create project with these settings?', true)) {
            $this->output->warning('Setup cancelled');
            return 1;
        }
        
        // Proceed with setup...
        $this->output->success('Project created successfully!');
        return 0;
    }
}
```

#### Secret Input (Passwords)

The `askSecret()` method hides user input, perfect for passwords and sensitive data:

```php
// Ask for password (input will be hidden)
$password = $this->input->askSecret('Enter password');

// Confirm password
$confirm = $this->input->askSecret('Confirm password');

if ($password !== $confirm) {
    $this->output->error('Passwords do not match');
    return 1;
}
```

#### Choice Selection

The `choice()` method presents options for selection:

```php
// Simple choice from array
$colors = ['Red', 'Green', 'Blue'];
$color = $this->input->choice('Pick a color:', $colors, 'Blue');

// Associative array (key => display value)
$environments = [
    'dev' => 'Development',
    'staging' => 'Staging', 
    'prod' => 'Production'
];
$env = $this->input->choice('Select environment:', $environments, 'dev');

// Multiple selection
$features = [
    'api' => 'REST API',
    'auth' => 'Authentication',
    'cache' => 'Caching'
];
$selected = $this->input->choice(
    'Select features to enable:',
    $features,
    null,        // No default
    true         // Allow multiple
);
// Returns array like: ['api', 'auth']

// Users can select by:
// - Number: Type "1" for first option
// - Key: Type "dev" for development
// - Value: Type "Development" (case-insensitive)
// - Multiple: "1,3" or "api,cache" (when multiple allowed)
```

## How Command Discovery Works

1. **Installation Detection**: When `neuron` is run, the ComponentLoader scans for installed packages
2. **Package Filtering**: Only `neuron-php/*` packages are considered
3. **Configuration Check**: Each package's `composer.json` is checked for CLI configuration
4. **Provider Loading**: If a CLI provider is found, its `register()` method is called
5. **Direct Registration**: Any directly listed commands are registered
6. **Project Commands**: The root project's `composer.json` is also checked

This automatic discovery means:
- No manual registration needed
- Commands available immediately after component installation
- Clean separation between components
- No conflicts between component commands

## Error Handling

Commands should return appropriate exit codes:

- `0` - Success
- `1` - General error
- `2` - Misuse of command
- `126` - Command cannot execute
- `127` - Command not found

Example:

```php
public function execute(): int
{
    try {
        // Command logic...
        return 0;
    } catch (ValidationException $e) {
        $this->output->error('Validation failed: ' . $e->getMessage());
        return 2;
    } catch (\Exception $e) {
        $this->output->error('An error occurred: ' . $e->getMessage());
        return 1;
    }
}
```

## Testing Your Commands

```php
use PHPUnit\Framework\TestCase;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;

class MakeControllerCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $command = new MakeControllerCommand();
        
        // Mock input
        $input = new Input(['UserController', '--resource']);
        $output = new Output(false); // No colors for testing
        
        $command->setInput($input);
        $command->setOutput($output);
        $command->configure();
        $input->parse($command);
        
        $exitCode = $command->execute();
        
        $this->assertEquals(0, $exitCode);
    }
}
```

## Contributing

When adding new features to the CLI component:

1. Extend the `Command` base class for new commands
2. Add tests in the `tests/` directory
3. Update this README with new features
4. Follow PSR-4 naming conventions
5. Use the existing code style (tabs, spaces etc)

## License

This component is part of the Neuron PHP Framework and is released under the MIT License.
