# AutoProctor - Developer Setup

Quick guide to get up and running with development.

## Prerequisites

- **Moodle 4.1+** local installation (see [MAMP Setup](#mamp-setup-macos) below)
- **PHP 8.0+**
- **Node.js 16+**
- **AutoProctor credentials** - Contact the team for dev/test credentials

## Getting Moodle

Clone the official Moodle repository:

```bash
git clone https://github.com/moodle/moodle.git ~/Projects/moodle
cd ~/Projects/moodle
git checkout MOODLE_401_STABLE  # or MOODLE_404_STABLE for 4.4
```

Then set up a local server (MAMP, Docker, etc.) to serve it.

## MAMP Setup (macOS)

If you need a local PHP/MySQL server:

1. **Install [MAMP](https://www.mamp.info/en/downloads/)**
2. **Set Document Root** → MAMP Preferences → Web Server → Set to your Moodle directory
3. **Fix max_input_vars** (Moodle requires 5000+):
  ```
   # Edit: /Applications/MAMP/bin/php/php8.3.30/conf/php.ini
   max_input_vars = 5000
  ```
   Then restart MAMP.
4. **Create database** → phpMyAdmin → Create database `moodle` (user: `root`, pass: `root`)
5. **Run installer** → Visit [http://localhost:8888](http://localhost:8888) and follow the wizard

> **Video tutorials**: [youtube.com/@AaronTweeton](https://www.youtube.com/@AaronTweeton)

## Setup Aliases (Do This First!)

Add to `~/.zshrc` (or `~/.bashrc`):

```bash
# MAMP PHP (adjust path if using different PHP)
alias mphp='/Applications/MAMP/bin/php/php8.3.30/bin/php'

# Moodle commands (run from Moodle root)
alias mcache='mphp admin/cli/purge_caches.php'
alias mupgrade='mphp admin/cli/upgrade.php'
```

Then reload: `source ~/.zshrc`

## Quick Setup

```bash
# 1. Clone the repo
git clone git@github.com:socrateasehq/autoproctor-moodle.git ~/Projects/autoproctor-moodle

# 2. Symlink into Moodle
ln -s ~/Projects/autoproctor-moodle ~/Projects/moodle/mod/quiz/accessrule/autoproctor

# 3. Install grunt (from Moodle root)
cd ~/Projects/moodle && npm install

# 4. Install the plugin
mupgrade

# 5. Configure credentials in Moodle
#    Site Admin > Plugins > Quiz > AutoProctor Integration
```

## Daily Workflow

### After Changing Files


| File Changed                                  | Command                                                      |
| --------------------------------------------- | ------------------------------------------------------------ |
| `amd/src/*.js`                                | `npx grunt amd --component=quizaccess_autoproctor && mcache` |
| `lang/*.php`, `templates/*.mustache`, `*.php` | `mcache`                                                     |
| `version.php`, `db/*`                         | `mupgrade`                                                   |


### Building JavaScript

```bash
cd ~/Projects/moodle
npx grunt amd --component=quizaccess_autoproctor
mcache
```

## Dev vs Production

The code currently uses **development** endpoints. Before release, update `amd/src/proctoring.js`:


| Setting     | Dev                               | Prod                             |
| ----------- | --------------------------------- | -------------------------------- |
| SDK URL     | `ap-development.s3.amazonaws.com` | `ap-production.s3.amazonaws.com` |
| Domain      | `dev.autoproctor.co`              | `autoproctor.co`                 |
| Environment | `development`                     | `production`                     |


## Testing Checklist

- Enable proctoring on a quiz
- Start quiz as student, grant permissions
- Complete and submit quiz
- View report as teacher

## Troubleshooting

**"Credentials not set"** → Check plugin settings for Client ID/Secret

**SDK won't load** → Check browser console, verify CDN URLs accessible

**Changes not showing** → Run `mcache`, hard refresh browser

---

For detailed architecture, database schema, and advanced troubleshooting, see [ARCHITECTURE.md](ARCHITECTURE.md).