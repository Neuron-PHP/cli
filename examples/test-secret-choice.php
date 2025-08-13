#!/usr/bin/env php
<?php

/**
 * Test script to demonstrate askSecret and choice methods
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;

// Create input and output instances
$input = new Input($argv);
$output = new Output();

$output->title('Secret and Choice Input Demo');

// Check if we're in interactive mode
if (!$input->isInteractive()) {
	$output->error('This script requires an interactive terminal');
	exit(1);
}

// Test askSecret for password
$output->section('Password Input');
$output->info('Enter a password (characters will be hidden):');
$password = $input->askSecret('Password');
$output->success('Password received (length: ' . strlen($password) . ' characters)');

// Confirm password
$output->info('Please confirm your password:');
$confirmPassword = $input->askSecret('Confirm Password');

if ($password === $confirmPassword) {
	$output->success('Passwords match!');
} else {
	$output->warning('Passwords do not match!');
}

// Test choice with simple array
$output->section('Simple Choice');
$colors = ['Red', 'Green', 'Blue', 'Yellow', 'Purple'];
$favoriteColor = $input->choice('What is your favorite color?', $colors, 'Blue');
$output->success("You selected: {$favoriteColor}");

// Test choice with associative array
$output->section('Associative Choice');
$environments = [
	'dev' => 'Development',
	'staging' => 'Staging',
	'prod' => 'Production'
];
$environment = $input->choice('Select deployment environment:', $environments, 'dev');
$output->success("You selected: {$environment} ({$environments[$environment]})");

// Test multiple choice
$output->section('Multiple Choice');
$features = [
	'auth' => 'Authentication',
	'api' => 'REST API',
	'websocket' => 'WebSocket Support',
	'cache' => 'Caching Layer',
	'queue' => 'Job Queue',
	'mail' => 'Email Service'
];
$output->info('Select features to install (comma-separated):');
$selectedFeatures = $input->choice('Which features would you like to enable?', $features, null, true);

if (is_array($selectedFeatures) && count($selectedFeatures) > 0) {
	$output->success('Selected features:');
	foreach ($selectedFeatures as $feature) {
		$output->write("  - {$feature}: {$features[$feature]}");
	}
} else {
	$output->warning('No features selected');
}

// Test choice with no default (forces selection)
$output->section('Required Choice');
$databases = ['MySQL', 'PostgreSQL', 'SQLite', 'MongoDB'];
$database = $input->choice('Select a database (required):', $databases);
$output->success("Database selected: {$database}");

$output->newLine();
$output->success('Demo completed!');