# AutoProctor - Architecture & Technical Reference

Detailed technical documentation for the AutoProctor Moodle plugin.

## How It Works

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

1. Student visits quiz → sees proctoring notice
2. Clicks "Attempt quiz" → preflight form with consent
3. AutoProctor SDK initializes, requests permissions
4. Form submit → SDK starts monitoring, quiz loads in iframe
5. Session record created in database
6. Quiz navigation in iframe, SDK monitors main window
7. Quiz submit → SDK stops, redirects to review page

## File Structure

```
autoproctor/
├── rule.php                 # Main plugin class
├── settings.php             # Admin settings
├── version.php              # Version & dependencies
├── create_session.php       # AJAX endpoint for sessions
├── amd/
│   └── src/proctoring.js    # Frontend JavaScript (AMD)
├── db/
│   ├── install.xml          # Database schema
│   ├── upgrade.php          # Migrations
│   └── access.php           # Capabilities
├── lang/en/                 # Language strings
└── templates/               # Mustache templates
```

## Database Schema

### `quizaccess_autoproctor` (per-quiz settings)

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Primary key |
| `quiz_id` | int | FK to quiz.id |
| `proctoring_enabled` | int(1) | 0/1 |
| `tracking_options` | text | JSON |
| `timecreated` | int | Unix timestamp |
| `timemodified` | int | Unix timestamp |

### `quizaccess_autoproctor_sessions` (per-attempt)

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Primary key |
| `quiz_id` | int | FK to quiz.id |
| `quiz_attempt_id` | int | FK to quiz_attempts.id |
| `test_attempt_id` | char(64) | ID sent to AutoProctor API |
| `tracking_options` | text | JSON for this session |
| `started_at` | int | Session start timestamp |

### Tracking Options JSON

```json
{
  "audio": true,
  "numHumans": true,
  "tabSwitch": true,
  "testTakerPhoto": true,
  "photosAtRandom": false,
  "recordSession": true,
  "recordTestTaker": false,
  "detectMultipleScreens": true,
  "forceFullScreen": false
}
```

## Key Functions

### rule.php

| Method | Purpose |
|--------|---------|
| `make()` | Factory - returns instance if proctoring enabled |
| `add_settings_form_fields()` | Adds options to quiz edit form |
| `save_settings()` | Saves settings to DB |
| `is_preflight_check_required()` | Returns true on view.php |
| `add_preflight_check_form_fields()` | Consent checkbox, SDK init |
| `setup_attempt_page()` | Injects report tabs on review.php |

### proctoring.js

| Function | Purpose |
|----------|---------|
| `init()` | Initialize AutoProctor SDK |
| `loadReport()` | Load proctoring report |
| `addReportButton()` | Create tabbed interface on review page |
| `getCredentials()` | Generate HMAC-SHA256 credentials |

## External Dependencies

- **AutoProctor SDK**: Loaded from `cdn.autoproctor.co` (production) or `ap-development.s3.ap-south-1.amazonaws.com` (development)

## Moodle Compatibility

Handles Moodle 4.x differences with class aliases:

```php
if (class_exists('\mod_quiz\local\access_rule_base')) {
    // Moodle 4.2+
} else {
    // Moodle 4.0-4.1
}
```

## MAMP Setup (macOS)

1. Install [MAMP](https://www.mamp.info/)
2. Set Document Root to Moodle directory
3. Create database `moodle`
4. Set `max_input_vars = 5000` in php.ini
5. Run installer at http://localhost:8888

## Database Troubleshooting

### Schema out of sync

```sql
DROP TABLE IF EXISTS mdl_quizaccess_autoproctor;
DROP TABLE IF EXISTS mdl_quizaccess_autoproctor_sessions;
```

Then bump `version.php` and run `mupgrade`.

### "No upgrade needed" but table missing

Moodle tracks version in `mdl_config_plugins`. Bump version number to force upgrade.

## Version History

| Version | Changes |
|---------|---------|
| 2025022502 | Fixed sessions table schema |
| 2025022501 | Fixed install.xml schema mismatch |
| 2024120702 | Previous stable |

## Contributing

1. Fork repository
2. Create feature branch
3. Follow Moodle coding standards
4. Test thoroughly
5. Submit PR

## License

GNU GPL v3 - http://www.gnu.org/copyleft/gpl.html
