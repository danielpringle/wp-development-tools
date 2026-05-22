# DPUK CLI Commands - WP-CLI Commands

**A collection of custom WP-CLI commands for WordPress development and maintenance.**

This plugin provides a library of useful WP-CLI commands that are automatically discovered and registered. All commands are located in the `includes/commands/` directory and follow a consistent structure.

---

## Installation

All commands are part of the **DPUK CLI Commands** plugin and are automatically loaded when the plugin is active.

1. Ensure the plugin is installed and activated
2. Ensure WP-CLI is installed and available
3. Commands are ready to use!

---

## Available Commands

- [`dpuk-find-block`](#dpuk-find-block) - Find which posts contain a specific block
- [`dpuk-perf-slow-queries`](#dpuk-perf-slow-queries) - Profile slow database queries

---

## dpuk-find-block

**Find which posts contain a specific Gutenberg block.**

Searches the WordPress posts table to identify posts that use a specific block within a date range. Useful for auditing block usage, finding posts that need updates, or tracking block adoption.

### Basic Usage

```bash
wp dpuk-find-block
```

Searches for the default block (`dpuk/insert-post-link`) in posts from the last 30 days.

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--block=<name>` | Block name to search for (e.g., `wp:heading`, `my-plugin/my-block`) | `dpuk/insert-post-link` |
| `--start-date=<date>` | Start date in `Y-m-d` format | 30 days ago |
| `--end-date=<date>` | End date in `Y-m-d` format | Today |

### Examples

```bash
# Find default block in last 30 days
wp dpuk-find-block

# Search for a specific block
wp dpuk-find-block --block=wp:heading

# Search within a specific date range
wp dpuk-find-block --start-date=2023-10-10 --end-date=2025-07-01

# Combine options
wp dpuk-find-block --start-date=2023-10-10 --block=wp:heading

# Save output to file
wp dpuk-find-block --start-date=2023-10-10 --block=wp:heading | tee output.log

# Get help
wp help dpuk-find-block
```

### Output

The command outputs:
- The block name being searched
- The date range being queried
- A list of post IDs containing the block
- A success message with the total count

**Example output:**
```
Scanning posts...
Block: wp:heading
Start date: 2023-10-10
End date: 2024-01-15
Post IDs:
123
456
789
Success: Block wp:heading found in 3 posts :)
```

### Use Cases

- Audit which posts use a specific block
- Find posts that need to be updated after block changes
- Track block adoption over time
- Identify posts for migration or cleanup

---

## dpuk-perf-slow-queries

**A local-development WP-CLI command to profile slow WordPress database queries.**

Simulate frontend, admin, or specific URLs, optionally as logged-in users, and inspect the queries that WordPress executes. Perfect for detecting N+1 queries, slow plugin or theme queries, and WooCommerce performance issues.

> **Note:** This command is intended for **local development only**. It uses `SAVEQUERIES` which adds performance overhead. Do **not** run on production.

### Features

- List slow database queries with execution time and call counts
- Group identical queries to detect repeated queries (N+1 issues)
- Detect the source (plugin, theme, core)
- Filter by request type: frontend, admin, or cron
- Simulate logged-in users for role-specific profiling
- Profile specific URLs (e.g., `/shop`, `/blog`) locally
- Output results in multiple formats: `table`, `json`, `csv`, `yaml`
- Optional call stack for debugging

### Basic Usage

```bash
wp dpuk-perf-slow-queries
```

Lists all slow queries in the current WordPress request using default threshold (0.05s).

### Options

| Flag | Description |
|------|-------------|
| `--threshold=<seconds>` | Minimum query execution time to show (default 0.05) |
| `--group` | Group identical queries together |
| `--limit=<n>` | Limit results (default 20) |
| `--format=<format>` | Output format: `table`, `json`, `csv`, `yaml` (default: table) |
| `--stack` | Show abbreviated call stack for debugging |
| `--request=<type>` | Filter by request type: `frontend`, `admin`, `cron` |
| `--auto-savequeries` | Enable SAVEQUERIES automatically for this run |

### Setup

#### About SAVEQUERIES

This command requires `SAVEQUERIES` to be enabled. When enabled, WordPress stores all database queries in `$wpdb->queries` along with execution time and stack traces. This allows the profiler to analyze query performance.

**⚠️ Important:** `SAVEQUERIES` adds performance overhead and should **never** be enabled in production. It's intended for local development and debugging only.

#### Enabling SAVEQUERIES

You have three options:

1. **Enable in `wp-config.php` (Recommended for local dev):**
   ```php
   // Enable query tracking for performance profiling (local/dev only)
   if ( ! defined( 'SAVEQUERIES' ) ) {
       $env_type = defined( 'WP_ENVIRONMENT_TYPE' ) ? WP_ENVIRONMENT_TYPE : 'production';
       define( 'SAVEQUERIES', in_array( $env_type, [ 'local', 'development' ], true ) );
   }
   ```
   This automatically enables it in local/development environments and disables it in production.

2. **Enable manually in `wp-config.php`:**
   ```php
   define( 'SAVEQUERIES', true );
   ```

3. **Use the `--auto-savequeries` flag** when running the command (enables it for that single run only).

### Profile Any Page

The general workflow is to first load a page (which populates the queries array), then run the profiler. Here's the pattern:

**Step 1: Load the page**
```bash
wp eval '
define("SAVEQUERIES", true);
$_SERVER["REQUEST_URI"] = "/your-page-slug/";
$_SERVER["QUERY_STRING"] = "";
require ABSPATH . "wp-blog-header.php";
'
```

**Step 2: Run the profiler**
```bash
wp dpuk-perf-slow-queries --group
```

**Or combine in one command:**
```bash
wp eval 'define("SAVEQUERIES", true); $_SERVER["REQUEST_URI"] = "/your-page-slug/"; require ABSPATH . "wp-blog-header.php";' && wp dpuk-perf-slow-queries --group
```

### Common Page Examples

#### Homepage

```bash
# Load homepage and profile queries
wp eval '
define("SAVEQUERIES", true);
$_SERVER["REQUEST_URI"] = "/";
$_SERVER["QUERY_STRING"] = "";
require ABSPATH . "wp-blog-header.php";
'
wp dpuk-perf-slow-queries --group
```

**One-liner:**
```bash
wp eval 'define("SAVEQUERIES", true); $_SERVER["REQUEST_URI"] = "/"; require ABSPATH . "wp-blog-header.php";' && wp dpuk-perf-slow-queries --group
```

#### About Page

```bash
# Load about page and profile queries
wp eval '
define("SAVEQUERIES", true);
$_SERVER["REQUEST_URI"] = "/about/";
$_SERVER["QUERY_STRING"] = "";
require ABSPATH . "wp-blog-header.php";
'
wp dpuk-perf-slow-queries --group
```

**One-liner:**
```bash
wp eval 'define("SAVEQUERIES", true); $_SERVER["REQUEST_URI"] = "/about/"; require ABSPATH . "wp-blog-header.php";' && wp dpuk-perf-slow-queries --group
```

**Note:** If `SAVEQUERIES` is already enabled in `wp-config.php`, you can omit the `define("SAVEQUERIES", true);` line from the eval command.

### Profile Specific URLs

Simulate visiting `/shop`:

```bash
wp eval '
$_SERVER["REQUEST_URI"] = "/shop/";
$_SERVER["QUERY_STRING"] = "";
require ABSPATH . "wp-blog-header.php";
'
wp dpuk-perf-slow-queries --group
```

Profile `/blog?page=2`:

```bash
wp eval '
$_SERVER["REQUEST_URI"] = "/blog/page/2/";
require ABSPATH . "wp-blog-header.php";
'
wp dpuk-perf-slow-queries --group
```

### Simulate Logged-In Users

Profile `/shop` as an admin:

```bash
wp eval '
wp_set_current_user(1);
$_SERVER["REQUEST_URI"] = "/shop/";
require ABSPATH . "wp-blog-header.php";
'
wp dpuk-perf-slow-queries --group
```

Profile as a specific role (e.g., customer):

```bash
wp eval '
$user = get_users(["role" => "customer", "number" => 1])[0];
wp_set_current_user($user->ID);
$_SERVER["REQUEST_URI"] = "/shop/";
require ABSPATH . "wp-blog-header.php";
'
wp dpuk-perf-slow-queries --group
```

### Group Queries to Detect N+1 Issues

```bash
wp dpuk-perf-slow-queries --group --threshold=0.02 --limit=10
```

- Groups identical queries together
- Shows total execution time and average per call
- Highlights queries executed multiple times

### Output Formats

```bash
# Table (default)
wp dpuk-perf-slow-queries --group

# JSON (for automation)
wp dpuk-perf-slow-queries --format=json

# CSV (exportable)
wp dpuk-perf-slow-queries --format=csv > slow-queries.csv

# YAML
wp dpuk-perf-slow-queries --format=yaml
```

### Example Workflow

1. Load a page or URL to populate `$wpdb->queries`:

```bash
wp eval '
$_SERVER["REQUEST_URI"] = "/shop/";
require ABSPATH . "wp-blog-header.php";
'
```

2. Run slow queries profiler:

```bash
wp dpuk-perf-slow-queries --group --stack --threshold=0.05
```

3. Identify slow or repeated queries:
   - Plugins adding unnecessary queries
   - Missing indexes on meta tables
   - WooCommerce product loops with N+1 `get_post_meta()` calls

---

## Command Structure

All commands in this library follow a consistent structure:

1. **Automatic Discovery**: Commands are automatically discovered from the `includes/commands/` directory
2. **Naming Convention**: Command files follow the pattern `class-{command-name}.php`
3. **Auto-Registration**: Commands register themselves in their constructor
4. **Namespace**: All commands use the `DPUKCLICommands` namespace

### Adding New Commands

To add a new command:

1. Create a new file in `includes/commands/` following the pattern: `class-your-command-name.php`
2. Use the following structure:

```php
<?php
namespace DPUKCLICommands;

use WP_CLI;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    class Your_New_Command {
        private $command_name = 'dpuk-your-command';
        
        public function __construct() {
            WP_CLI::add_command( $this->command_name, $this );
        }
        
        public function __invoke( $args, $assoc_args ) {
            // Your command logic
        }
    }
    
    new Your_New_Command();
}
```

3. The command will be automatically discovered and registered when the plugin loads.

---

## Support

For issues, questions, or contributions, please refer to the main plugin documentation or repository.

