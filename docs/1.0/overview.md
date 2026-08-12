# Overview

---

- [First Section](#section-1)

<a name="section-1"></a>
## First Section

# Introduction

Welcome to the documentation for our Modular Application Framework. This framework provides a robust foundation for developing modular Laravel applications with integrated role-based permissions management.

## What is the Modular App Framework?

The Modular App Framework is a Laravel-based solution designed to accelerate the development of complex, modular applications. Built on a forked version of Laravel Modules and integrated with Spatie Permissions, this framework provides a solid architecture that allows developers to:

- Create independent, self-contained modules
- Manage module-specific permissions and roles
- Generate standardized controllers and API routes
- Define data structures using JSON Schema

Our framework eliminates the repetitive tasks typically associated with setting up modular applications, allowing you to focus on building your application's unique functionality.

## Core Features

### Modular Architecture

The framework uses a modified version of Laravel Modules to organize your application into discrete, reusable modules. Each module functions as a mini-application that can be enabled, disabled, or transferred between projects.

### Role-Based Access Control

Built on Spatie Permissions, our framework provides a comprehensive role and permission system that operates at the module level. This allows you to:

- Define granular permissions for each module
- Create role hierarchies specific to modules
- Assign users different roles across different modules
- Control access to features with precision

### Menu-Driven Development

One of the unique aspects of our framework is the menu-driven approach to application structure. Each module contains a `menu.php` file that serves as the central configuration point. From this file, the framework:

- Automatically generates appropriate routes
- Creates controller scaffolding
- Establishes necessary API endpoints
- Configures permission requirements

### Schema-Based Database Management

Database structures are defined using JSON Schema specifications. This approach provides:

- Consistent database migrations across modules
- Validation of data structures at the schema level
- Self-documenting data models
- Simplified database version control

## Getting Started

To begin developing with the Modular App Framework, follow our [Installation Guide](./installation.md) and review the [Basic Concepts](./basic-concepts.md) documentation. Once familiar with the framework, you can explore specific features in detail through our module-specific guides.

## Use Cases

This framework is ideal for applications that:

- Need to scale in complexity over time
- Require granular permission systems
- Benefit from modular development practices
- Are maintained by multiple development teams
- Need standardized API endpoints

By providing a structured yet flexible foundation, the Modular App Framework helps development teams create maintainable, extensible applications with minimal boilerplate code.