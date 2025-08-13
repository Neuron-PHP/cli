#!/usr/bin/env php
<?php

/**
 * Test script to demonstrate interactive input in CLI commands
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;

// Create input and output instances
$input = new Input($argv);
$output = new Output();

$output->title('Interactive Input Test');

// Check if we're in interactive mode
if (!$input->isInteractive()) {
	$output->error('This script requires an interactive terminal');
	exit(1);
}

// Test basic ask
$name = $input->ask('What is your name', 'Guest');
$output->success("Hello, {$name}!");

// Test ask without default
$color = $input->ask('What is your favorite color');
if ($color) {
	$output->info("Nice! {$color} is a great color!");
}

// Test confirmation
if ($input->confirm('Do you like PHP', true)) {
	$output->success('Great! PHP is awesome!');
} else {
	$output->comment('That\'s okay, everyone has preferences!');
}

// Test another confirmation with default false
if ($input->confirm('Do you want to see a progress bar demo', false)) {
	$progress = $output->createProgressBar(20);
	$progress->start();
	
	for ($i = 0; $i < 20; $i++) {
		usleep(50000);
		$progress->advance();
	}
	
	$progress->finish();
	$output->success('Progress bar demo completed!');
}

// Test reading raw input
$output->write('');
$output->question('Type a message and press Enter:');
$message = $input->readLine();
if ($message) {
	$output->info('You typed: ' . trim($message));
}

$output->write('');
$output->success('Interactive input test completed!');