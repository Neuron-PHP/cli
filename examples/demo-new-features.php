#!/usr/bin/env php
<?php

/**
 * Quick demo of askSecret and choice methods
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;

$input = new Input($argv);
$output = new Output();

$output->title('New Input Features Demo');

// Demo 1: Secret Input
$output->section('Password Input Demo');
$output->info('Type a password (it will be hidden):');
$secret = $input->askSecret('Secret');
$output->success('Received ' . strlen($secret) . ' characters');
$output->newLine();

// Demo 2: Simple Choice
$output->section('Simple Choice Demo');
$choice = $input->choice(
    'Pick your favorite programming language:',
    ['PHP', 'JavaScript', 'Python', 'Go', 'Rust'],
    'PHP'
);
$output->success("You picked: {$choice}");
$output->newLine();

// Demo 3: Multiple Choice
$output->section('Multiple Choice Demo');
$output->info('You can select multiple by entering comma-separated values');
$multiple = $input->choice(
    'Select skills you have:',
    ['Backend', 'Frontend', 'DevOps', 'Database', 'Security'],
    null,
    true
);
$output->success('Selected: ' . implode(', ', (array)$multiple));

$output->newLine();
$output->success('Demo complete!');