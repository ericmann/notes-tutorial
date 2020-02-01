# PHP Security Tutorial - Notes

This is an iterative workshop/tutorial explaining PHP security. It's divided into multiple _modules_, each of which is a standalone lesson as part of a three-hour workshop documented through [these slides] [slides].

Each module is broken into two directories:
- `/server` - The actual PHP server, runnable through `php -S localhost:8888 -t module-N/server`
- `/client` - A command line client, also written in PHP, which interacts with the server component.


## Installation

Composer dependencies are bundled in the repository to make it easier to clone and get started with this project. If for any reason you want to _update_ your dependencies, merely do so with `composer update`.

The first step is to install Composer dependencies by running `composer install` in the root directory of the tutorial.

The modules themselves are self-contained and share their dependencies.

## Understanding the Lessons

Each lesson is built to cover a specific topic regarding PHP security. As such, there are several placeholder @TODOs throughout the code that are meant for you to complete. Each is documented explaining what's expected from you to complete the task.

The lessons are structured into the following modules:

### 1. Credentials Management

`module-1`

- `.env` files
- Flat configuration files

### 2. Authentication

`module-2`

- Password management
- Password storage
- Password hashing

### 3. Session Management

`module-3`

- PHP session configuration

### 4. Data - Validation & Sanitization

`module-4`

- Input validation
- Output sanitization

### 5. Encryption

`module-5`

- File encryption
- Database encryption
- Blind indicies

## In Addition

Attendees of a live workshop presentation will note the sections on "Environment" and "Server Hardening" from [the workshop slide deck] [slides]. This is an intentional omission. Environment configuration is baked into this project directly and is something we'll discuss in person. Server hardening is also a _production_ configuration topic and is not easily worked through in a workshop environment.

[slides]: https://speakerdeck.com/ericmann/evolution-of-php-security-2020