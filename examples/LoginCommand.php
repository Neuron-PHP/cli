<?php

namespace Examples;

use Neuron\Cli\Commands\Command;

/**
 * Example command demonstrating askSecret and choice methods
 */
class LoginCommand extends Command
{
	public function getName(): string
	{
		return 'example:login';
	}
	
	public function getDescription(): string
	{
		return 'Demonstrates secure login with server selection';
	}
	
	public function execute(): int
	{
		$this->output->title('Secure Login Demo');
		
		// Check if interactive
		if (!$this->input->isInteractive()) {
			$this->output->error('Login requires an interactive terminal');
			return 1;
		}
		
		// Select server/environment
		$servers = [
			'local' => 'Local Development (localhost:8000)',
			'dev' => 'Development Server (dev.example.com)',
			'staging' => 'Staging Server (staging.example.com)',
			'prod' => 'Production Server (api.example.com)'
		];
		
		$this->output->section('Server Selection');
		$server = $this->input->choice(
			'Select server to connect to:',
			$servers,
			'local'
		);
		
		$this->output->info("Connecting to: {$servers[$server]}");
		$this->output->newLine();
		
		// Get credentials
		$this->output->section('Authentication');
		
		// Username
		$username = $this->input->ask('Username');
		if (empty($username)) {
			$this->output->error('Username is required');
			return 1;
		}
		
		// Password (hidden input)
		$password = $this->input->askSecret('Password');
		if (empty($password)) {
			$this->output->error('Password is required');
			return 1;
		}
		
		// Two-factor authentication
		if ($this->input->confirm('Do you have 2FA enabled?', false)) {
			$code = $this->input->ask('Enter 2FA code');
			if (!preg_match('/^\d{6}$/', $code)) {
				$this->output->error('Invalid 2FA code format');
				return 1;
			}
		}
		
		// Remember login?
		$rememberOptions = [
			'no' => 'No, ask every time',
			'session' => 'Remember for this session only',
			'day' => 'Remember for 1 day',
			'week' => 'Remember for 1 week',
			'month' => 'Remember for 1 month'
		];
		
		$remember = $this->input->choice(
			'How long should we remember your login?',
			$rememberOptions,
			'session'
		);
		
		// Simulate authentication
		$this->output->newLine();
		$this->output->info('Authenticating...');
		
		// Create progress bar for effect
		$progress = $this->output->createProgressBar(5);
		$progress->start();
		
		for ($i = 0; $i < 5; $i++) {
			usleep(200000); // Simulate network delay
			$progress->advance();
		}
		
		$progress->finish();
		$this->output->newLine();
		
		// Success
		$this->output->success("Successfully logged in as {$username}!");
		$this->output->info("Connected to: {$servers[$server]}");
		$this->output->info("Session will be remembered: {$rememberOptions[$remember]}");
		
		// Additional options
		$this->output->newLine();
		$this->output->section('Post-Login Options');
		
		$actions = [
			'dashboard' => 'View Dashboard',
			'profile' => 'Edit Profile',
			'settings' => 'Change Settings',
			'logs' => 'View Activity Logs',
			'logout' => 'Logout'
		];
		
		$this->output->info('What would you like to do next?');
		$selectedActions = $this->input->choice(
			'Select actions (you can select multiple):',
			$actions,
			null,
			true // Allow multiple selections
		);
		
		if (!empty($selectedActions)) {
			$this->output->success('Selected actions:');
			foreach ((array)$selectedActions as $action) {
				$this->output->write("  • {$actions[$action]}");
			}
		}
		
		return 0;
	}
}