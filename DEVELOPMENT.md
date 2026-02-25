# AutoProctor - Developer Documentation

This document covers the technical architecture, file structure, and development setup for the AutoProctor plugin.

## Architecture Overview

AutoProctor is a Moodle quiz access rule plugin that:

1. Adds proctoring settings to quiz configuration
2. Shows a preflight consent form before quiz attempts
3. Initializes the AutoProctor SDK and monitors students during the quiz
4. Stores session data linked to quiz attempts
5. Provides a report viewing interface for teachers

### How It Works

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   Quiz View     │────▶│  Preflight Form  │────▶│  Quiz Attempt   │
│   (view.php)    │     │  (consent + SDK) │     │  (in iframe)    │
└─────────────────┘     └──────────────────┘     └─────────────────┘
                               │                        │
                               ▼                        ▼
                        ┌──────────────┐         ┌──────────────┐
                        │ AutoProctor  │         │   Session    │
                        │    SDK       │◀───────▶│   Created    │
                        └──────────────┘         └──────────────┘
```

1. Student visits quiz page → sees proctoring notice
2. Clicks "Attempt quiz" → preflight form loads with consent checkbox
3. AutoProctor SDK initializes, requests permissions
4. On form submit → SDK starts monitoring, quiz loads in iframe
5. Session record created in database
6. Quiz navigation happens inside iframe while SDK monitors main window
7. On quiz submit → SDK stops, redirects to review page

## File Structure

```
autoproctor/
├── rule.php                 # Main plugin class (access rule implementation)
├── settings.php             # Admin settings page
├── version.php              # Plugin version and dependencies
├── create_session.php       # AJAX endpoint to create proctoring sessions
├── loadreport.php           # Report viewing page
├── amd/
│   ├── src/
│   │   └── proctoring.js    # Frontend JavaScript (AMD module)
│   └── build/
│       ├── proctoring.min.js
│       └── proctoring.min.js.map
├── db/
│   ├── install.xml          # Database schema
│   ├── upgrade.php          # Database migrations
│   └── access.php           # Capability definitions
├── lang/
│   └── en/
│       └── quizaccess_autoproctor.php  # Language strings
├── templates/
│   ├── autoproctor.mustache # Report view template
│   └── loader.mustache      # Loading screen template
├── README.md                # User documentation
└── DEVELOPMENT.md           # This file
```

## Database Schema

### `quizaccess_autoproctor`

Stores per-quiz proctoring settings.

| Field | Type | Description |
|-------|------|-------------|
| `id` | int(10) | Primary key |
| `quiz_id` | int(10) | FK to `quiz.id` |
| `proctoring_enabled` | int(1) | 0 = disabled, 1 = enabled |
| `tracking_options` | text | JSON object of enabled tracking options |
| `timecreated` | int(10) | Unix timestamp |
| `timemodified` | int(10) | Unix timestamp |

### `quizaccess_autoproctor_sessions`

Stores per-attempt proctoring session data.

| Field | Type | Description |
|-------|------|-------------|
| `id` | int(10) | Primary key |
| `quiz_id` | int(10) | FK to `quiz.id` |
| `quiz_attempt_id` | int(10) | FK to `quiz_attempts.id` |
| `test_attempt_id` | char(64) | Unique ID sent to AutoProctor API |
| `tracking_options` | text | JSON of tracking options for this session |
| `started_at` | int(10) | Session start timestamp |
| `timecreated` | int(10) | Unix timestamp |
| `timemodified` | int(10) | Unix timestamp |

### Tracking Options JSON Structure

```json
{
  "audio": true,
  "numHumans": true,
  "tabSwitch": true,
  "testTakerPhoto": true,
  "photosAtRandom": false,
  "showCamPreview": true,
  "captureSwitchedTab": true,
  "recordSession": true,
  "detectMultipleScreens": true,
  "forceFullScreen": false
}
```

## Key Classes & Functions

### `rule.php` - `quizaccess_autoproctor`

| Method | Purpose |
|--------|---------|
| `make()` | Factory method - returns instance if proctoring enabled |
| `add_settings_form_fields()` | Adds tracking options to quiz edit form |
| `save_settings()` | Saves proctoring settings to database |
| `description()` | Returns info shown on quiz view page |
| `is_preflight_check_required()` | Returns true on view.php if proctoring enabled |
| `add_preflight_check_form_fields()` | Adds consent checkbox, initializes SDK |
| `validate_preflight_check()` | Validates consent was given |
| `get_ap_settings()` | Retrieves quiz proctoring settings |
| `get_ap_session()` | Retrieves session for an attempt |
| `get_lookup_key()` | Generates unique key for report lookup |

### `proctoring.js` - AMD Module

| Function | Purpose |
|----------|---------|
| `init()` | Main entry point - initializes AutoProctor SDK |
| `loadReport()` | Loads proctoring report for an attempt |
| `getCredentials()` | Generates HMAC-SHA256 hashed credentials |
| `addSubmitPreflightEvent()` | Intercepts form submit, starts proctoring |
| `handleIframeUrlChange()` | Handles navigation within quiz iframe |
| `createNewApSession()` | POSTs to create_session.php |

## External Dependencies

### AutoProctor SDK

Loaded from CDN:
- JS: `https://ap-development.s3.amazonaws.com/autoproctor.4.3.0.min.js`
- CSS: `https://ap-development.s3.amazonaws.com/autoproctor.4.3.0.min.css`

### CryptoJS

Used for HMAC-SHA256 hashing of test attempt IDs:
- `https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js`

## Development Setup

### Prerequisites

- Local Moodle installation (4.1+)
- PHP 8.0+
- MySQL/MariaDB or PostgreSQL

### Using MAMP (macOS)

1. Install [MAMP](https://www.mamp.info/)
2. Set Document Root to your Moodle directory
3. Create database named `moodle`
4. Set `max_input_vars = 5000` in php.ini
5. Run Moodle installer at http://localhost:8888

### CLI Commands

Use MAMP's PHP for CLI commands. Add these aliases to `~/.zshrc`:

```bash
# PHP alias for MAMP
alias mphp='/Applications/MAMP/bin/php/php8.3.30/bin/php'

# Quick aliases for common Moodle commands (run from Moodle root)
alias mcache='/Applications/MAMP/bin/php/php8.3.30/bin/php admin/cli/purge_caches.php'
alias mupgrade='/Applications/MAMP/bin/php/php8.3.30/bin/php admin/cli/upgrade.php'
```

After adding, run `source ~/.zshrc` to reload.

**Usage:**

```bash
# Purge all caches (run after changing language strings, templates, etc.)
mcache

# Run database upgrades (run after changing version.php or db files)
mupgrade

# Or use mphp for any CLI script
mphp admin/cli/purge_caches.php
```

### Quick Reference: What to Run After Changes

| File Changed | Command to Run |
|--------------|----------------|
| `lang/en/*.php` (language strings) | `mcache` |
| `templates/*.mustache` | `mcache` |
| `amd/src/*.js` (JavaScript) | `npx grunt amd --component=quizaccess_autoproctor` then `mcache` |
| `version.php` | `mupgrade` |
| `db/install.xml` | `mupgrade` (for fresh install) |
| `db/upgrade.php` | Bump `version.php` first, then `mupgrade` |
| `rule.php` or other PHP | `mcache` (or just refresh if opcache disabled) |
| `settings.php` | `mcache` |
| `db/access.php` (capabilities) | `mupgrade` then `mcache` |

### Building JavaScript

After modifying `amd/src/proctoring.js`:

```bash
# From Moodle root
npx grunt amd --component=quizaccess_autoproctor
```

Or if using Moodle's grunt setup:

```bash
grunt amd
```

Then purge caches:

```bash
mcache
```

### Database Changes

1. Update `db/install.xml` with schema changes
2. Add migration to `db/upgrade.php`
3. Increment version in `version.php`
4. Run `mupgrade`

### Debugging

Enable Moodle debugging in `config.php`:

```php
@error_reporting(E_ALL | E_STRICT);
@ini_set('display_errors', '1');
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;
```

## Moodle Version Compatibility

The plugin handles differences between Moodle 4.x versions using class aliases:

```php
// Moodle 4.2+
if (class_exists('\mod_quiz\local\access_rule_base')) {
    class_alias('\mod_quiz\local\access_rule_base', '...');
// Moodle 4.0-4.1
} else {
    require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
    class_alias('\quiz_access_rule_base', '...');
}
```

## Testing

### Manual Testing Checklist

- [ ] Enable proctoring on a quiz
- [ ] Verify settings save correctly
- [ ] Start quiz as student, verify consent form appears
- [ ] Grant permissions, verify SDK initializes
- [ ] Navigate through quiz questions
- [ ] Submit quiz, verify proctoring stops
- [ ] View report as teacher

### Common Issues

**"Credentials not set" error:**
- Check `client_id` and `client_secret` in plugin settings

**SDK doesn't load:**
- Check browser console for errors
- Verify CDN URLs are accessible
- Check for Content Security Policy blocks

**Session not created:**
- Check `create_session.php` receives correct parameters
- Verify `sesskey` is valid
- Check database permissions

## Version History

See `db/upgrade.php` for database migration history.

| Version | Changes |
|---------|---------|
| 2024111106 | Added quiz_id, started_at, tracking_options to sessions |
| 2024111109 | Removed session_id and status fields |
| 2024111112 | Created quizaccess_autoproctor settings table |
| 2024120601 | Removed ended_at field |
| 2024120702 | Previous stable version |
| 2025022501 | Fixed install.xml schema mismatch (requireautoproctor → proctoring_enabled), added timecreated/timemodified fields |

## Troubleshooting Development Issues

### "Error reading from database" after schema changes

If you see database errors after modifying `install.xml`, the table schema may be out of sync:

1. **Check current table structure in phpMyAdmin**
2. **Drop and recreate the table:**
   ```sql
   DROP TABLE IF EXISTS mdl_quizaccess_autoproctor;
   DROP TABLE IF EXISTS mdl_quizaccess_autoproctor_sessions;
   ```
3. **Bump version number** in `version.php`
4. **Add upgrade step** in `db/upgrade.php` to recreate the table
5. **Run upgrade:**
   ```bash
   mupgrade
   ```

### Schema mismatch between install.xml and code

The `install.xml` is used for fresh installations. The `upgrade.php` handles existing installations. Both must match what the PHP code expects.

**Common field name issues:**
- `install.xml` field names must match what `save_settings()` and `get_ap_settings()` use
- If code uses `proctoring_enabled`, both install.xml and upgrade.php must use that name

### Upgrade says "No upgrade needed" but table is missing

This happens when you drop a table but don't bump the version:

1. Moodle tracks the installed version in `mdl_config_plugins`
2. If version hasn't changed, upgrade won't run
3. **Solution:** Bump `$plugin->version` in `version.php`

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make changes following Moodle coding standards
4. Test thoroughly
5. Submit a pull request

## License

GNU GPL v3 or later - http://www.gnu.org/copyleft/gpl.html
