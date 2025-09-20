# WeCoza Events Plugin

WordPress plugin for centralized notification system using PostgreSQL database integration.

## Features

- **Event-driven notification system** - Centralized event processing with idempotency
- **PostgreSQL database integration** - Modern database architecture with JSONB support
- **Email queue management** - Reliable email delivery with retry mechanisms
- **Security-focused implementation** - Comprehensive input validation and output escaping
- **Internationalization support** - Translation-ready with POT file
- **Classes integration** - Connects with existing PostgreSQL classes table
- **Audit trail** - Complete logging of all security and operational events

## Requirements

- WordPress 5.0+
- PHP 7.4+
- PostgreSQL 12+

## Installation

1. Clone repository to wp-content/plugins/
```bash
git clone git@github.com:yourdesigncoza/wecoza-events-plugin.git
```

2. Configure PostgreSQL connection in `app/Services/PostgreSQLDatabaseService.php`

3. Run PostgreSQL schema:
```bash
psql -U your_user -d your_db -f schema/postgresql_events_schema.sql
```

4. Activate plugin in WordPress admin

## Development

See `CLAUDE.md` for comprehensive development guidelines and architecture overview.

### Key Components

- **PostgreSQL Database Service** - PDO-based database abstraction
- **Event Processor** - Central event handling and routing
- **Security Service** - Input validation and sanitization utilities
- **Email Service** - WordPress mail integration with queueing

## Architecture

The plugin uses a dual-database architecture:
- WordPress MySQL for core WordPress functionality
- PostgreSQL for application data with advanced JSONB support

Events flow: Emission → Validation → Routing → Queueing → Processing → Delivery

## Contributing

Follow WordPress coding standards and ensure all security best practices are maintained.