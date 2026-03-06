# Security Policy

## External Dependencies

This plugin loads JavaScript from external CDN sources:

### AutoProctor SDK

- **Production**: `https://cdn.autoproctor.co/ap-entry-moodle.js`
- **Development**: `https://ap-development.s3.ap-south-1.amazonaws.com/ap-entry-moodle.js`

The SDK is required for proctoring functionality including webcam capture, audio recording, and screen monitoring.

## Data Collection

When proctoring is enabled, the following data may be collected and transmitted to AutoProctor servers:


| Data Type           | Purpose                                    | Storage Location    |
| ------------------- | ------------------------------------------ | ------------------- |
| Webcam photos       | Identity verification, presence monitoring | AutoProctor servers |
| Audio recordings    | Environment monitoring                     | AutoProctor servers |
| Screen recordings   | Activity monitoring                        | AutoProctor servers |
| Tab switch events   | Focus tracking                             | AutoProctor servers |
| Browser/device info | Compatibility checks                       | AutoProctor servers |


## Data Security

### Server-Side Hashing

All lookup keys are generated server-side using HMAC-SHA256 hashing. The client secret is never exposed to the browser.

### Session Management

- Unique test attempt IDs are generated for each quiz attempt
- Session data is linked to Moodle quiz attempts via foreign keys
- Sessions are isolated per quiz and per user

### Capability Controls

- Only users with `quizaccess/autoproctor:viewreport` capability can access proctoring reports
- Report access requires valid session key verification

## Third-Party Data Processing

By enabling AutoProctor, you acknowledge that:

1. User biometric data (facial images, voice) is processed by AutoProctor
2. Data is transmitted to and stored on AutoProctor servers
3. AutoProctor's privacy policy applies to data processing
4. You are responsible for obtaining appropriate consent from users

## Network Requirements

This plugin requires outbound internet access to:

- `*.autoproctor.co` - API and CDN
- `*.amazonaws.com` - Development SDK (if using development mode)

## Reporting Security Issues

To report a security vulnerability:

1. **Do not** create a public GitHub issue
2. Email security concerns to: [help@autoproctor.co](mailto:help@autoproctor.co)
3. Include detailed reproduction steps
4. Allow reasonable time for a fix before public disclosure

