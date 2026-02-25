# AutoProctor - Moodle Quiz Proctoring Plugin

AutoProctor is a quiz access rule plugin that integrates automated proctoring into Moodle quizzes. It monitors students during online exams using their camera, microphone, and screen to help prevent cheating and ensure exam integrity.

## Features

- **Automated Monitoring** - AI-powered proctoring that tracks student behavior during quizzes
- **Flexible Configuration** - Enable/disable specific tracking features per quiz
- **Student Consent** - Built-in consent workflow before exam starts
- **Proctoring Reports** - View detailed reports with trust scores and flagged violations
- **Seamless Integration** - Works within the standard Moodle quiz workflow

### Tracking Options

| Category | Options |
|----------|---------|
| **Activity** | Audio detection, Multiple person detection, Tab switch detection |
| **Camera** | Photo at test start, Random photos during test, Live camera preview |
| **Screen** | Screenshot on tab switch, Full session recording, Multiple monitor detection, Forced fullscreen |

## Requirements

- Moodle 4.1 or higher
- AutoProctor account with API credentials ([Get credentials](https://autoproctor.co/developers/register/))
- Students need: webcam, microphone, and a modern browser (Chrome/Firefox/Edge)

## Installation

### Method 1: Upload via Moodle Admin

1. Download the plugin ZIP file
2. Go to **Site Administration > Plugins > Install plugins**
3. Upload the ZIP file and click "Install plugin from the ZIP file"
4. Follow the on-screen prompts to complete installation

### Method 2: Manual Installation

1. Extract the plugin to `/mod/quiz/accessrule/autoproctor`
2. Visit **Site Administration > Notifications** to trigger the installation
3. Follow the on-screen prompts

## Configuration

### Step 1: Get API Credentials

1. Register at [autoproctor.co/developers/register](https://autoproctor.co/developers/register/)
2. After registration, you'll receive a **Client ID** and **Client Secret**

> **Note:** If you see the warning "AutoProctor key-pair (credentials) are not set" on a quiz page, it means you haven't configured the credentials yet.

### Step 2: Add API Credentials to Moodle

1. Go to **Site Administration > Plugins > Activity modules > Quiz > AutoProctor Integration**
2. Enter your **Client ID** and **Client Secret**
3. Optionally enable "Enable AutoProctor by default" for all new quizzes
4. Save changes

### Step 2: Enable on a Quiz

1. Edit any quiz and expand the **AutoProctor Settings** section
2. Set "Turn AutoProctor On" to **Yes**
3. Configure individual tracking options as needed
4. Save the quiz

## Usage

### For Teachers/Administrators

**Enabling Proctoring:**
1. Edit the quiz settings
2. Scroll to "AutoProctor Settings"
3. Enable proctoring and select desired tracking options
4. Save changes

**Viewing Reports:**
1. Go to the quiz page
2. Click the "View AutoProctor Results" button (visible to teachers/managers)
3. Review trust scores, flagged events, and session recordings

### For Students

1. Navigate to the proctored quiz
2. Click "Attempt quiz"
3. Review the permissions required (camera, microphone, screen)
4. Check the consent box and click "Start attempt"
5. Allow browser permissions when prompted
6. Wait for AutoProctor to initialize
7. Complete the quiz as normal
8. Submit the quiz - proctoring stops automatically

## Permissions

The plugin adds one capability:

| Capability | Description | Default Roles |
|------------|-------------|---------------|
| `quizaccess/autoproctor:viewreport` | View proctoring reports | Teacher, Editing Teacher, Manager |

## Troubleshooting

### "AutoProctor credentials are not set"
- Ensure Client ID and Client Secret are configured in plugin settings
- Verify credentials are correct from your AutoProctor dashboard

### Proctoring won't start
- Check browser permissions for camera/microphone/screen sharing
- Try a different browser (Chrome recommended)
- Disable browser extensions that might block permissions
- Ensure stable internet connection

### Students can't access the quiz
- Verify the quiz has AutoProctor enabled
- Check that students have accepted the consent checkbox
- Ensure students are using a compatible browser

## Privacy & Data

AutoProctor collects:
- Webcam photos/video
- Audio from microphone
- Screen recordings (if enabled)
- Tab switch events
- Multiple person detection events

All data is processed by AutoProctor's servers. Review [AutoProctor's Privacy Policy](https://autoproctor.co/privacy) for details on data handling and retention.

## Support

- **Documentation**: [AutoProctor Help Center](https://help.autoproctor.co/)
- **Issues**: Report bugs on the plugin repository
- **Contact**: support@autoproctor.co

## Development

See [DEVELOPMENT.md](DEVELOPMENT.md) for developer documentation.

## License

This plugin is licensed under the GNU GPL v3 or later.

http://www.gnu.org/copyleft/gpl.html

## Credits

Developed by [AutoProctor](https://autoproctor.co)
