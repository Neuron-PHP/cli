<?php

namespace Examples;

use Neuron\Cli\Commands\Command;

/**
 * Example command demonstrating interactive input capabilities
 */
class InteractiveCommand extends Command
{
	public function getName(): string
	{
		return 'example:interactive';
	}
	
	public function getDescription(): string
	{
		return 'Demonstrates interactive input capabilities';
	}
	
	public function configure(): void
	{
		$this->addOption('skip-confirm', 's', false, 'Skip confirmation prompts');
	}
	
	public function execute(): int
	{
		$this->output->title('Interactive Input Demo');
		
		// Check if we're in an interactive terminal
		if (!$this->input->isInteractive()) {
			$this->output->error('This command requires an interactive terminal');
			return 1;
		}
		
		// Ask for text input with default
		$name = $this->input->ask('What is your name', 'Anonymous User');
		$this->output->info("Hello, {$name}!");
		
		// Ask for input without default
		$project = $this->input->ask('What project are you working on');
		if (empty($project)) {
			$this->output->warning('No project specified');
			$project = 'unnamed-project';
		}
		
		// Ask for email with validation loop
		$email = '';
		while (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$email = $this->input->ask('Enter your email address');
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$this->output->error('Invalid email format. Please try again.');
				$email = '';
			}
		}
		
		// Confirm action (unless skipped)
		if (!$this->input->getOption('skip-confirm')) {
			$this->output->section('Summary');
			$this->output->write("Name: {$name}");
			$this->output->write("Project: {$project}");
			$this->output->write("Email: {$email}");
			$this->output->write('');
			
			if (!$this->input->confirm('Proceed with these details', true)) {
				$this->output->warning('Operation cancelled by user');
				return 1;
			}
		}
		
		// Simulate some work with progress bar
		$this->output->info('Processing...');
		$progress = $this->output->createProgressBar(10);
		$progress->start();
		
		for ($i = 0; $i < 10; $i++) {
			usleep(100000); // Simulate work
			$progress->advance();
		}
		
		$progress->finish();
		
		// Success message
		$this->output->success('Operation completed successfully!');
		
		// Final confirmation
		if ($this->input->confirm('Would you like to see the results', false)) {
			$this->output->table(
				['Field', 'Value'],
				[
					['Name', $name],
					['Project', $project],
					['Email', $email],
					['Status', 'Completed']
				]
			);
		}
		
		return 0;
	}
}