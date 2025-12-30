## 0.8.9 2025-12-30
* Testability refactoring: Added IInputReader abstraction for testing CLI commands with user input.
* Fixed potential stack overflow in StdinInputReader::choice() by replacing recursive retry with iterative loop.
* Added MAX_RETRY_ATTEMPTS constant to prevent infinite loops on invalid input.
* Fixed empty() bug where '0' was incorrectly treated as empty response.
* Added defensive guard in Command::getInputReader() to prevent uninitialized property access by lazily initializing Output when needed.
* CRITICAL: Added exception safety to secret() method with try-finally to ensure terminal echo is always restored, preventing broken terminal states.
* CRITICAL: Added TTY check (isTty() method) to prevent stty errors in non-interactive environments (CI/CD, piped input, automated scripts).

## 0.8.8 2025-11-28
## 0.8.7 2025-11-24
## 0.8.6 2025-11-22
* Added the initializer scaffold command.

## 0.8.5 2025-11-19
* Fixed a remaining config.yaml reference.

## 0.8.4 2025-11-12

* Minor bug fixes to support jobs.
* Renamed config.yaml to neuron.yaml

## 0.8.1 2025-11-04

* Added environment variable support commands.

## 0.1.5 2025-11-04
## 0.1.4 2025-08-14
## 0.1.3 2025-08-14
