# EML Web Browser

A PHP web application for viewing and browsing `.eml` email files organized in directories.

## Features

- 🔒 Secure login with hashed passwords
- 📁 Browse email directories with custom descriptions
- 📧 View email metadata (sender, date, subject)
- 👁️ Preview email content with proper decoding
- ⏱️ 6-hour session timeout
- 🔄 AJAX-based inline updates
- 🛡️ Path traversal protection

## Installation

1. Clone or copy this project to your web server
2. Ensure PHP is installed (no specific version required, but PHP 7.4+ recommended)
3. Set up users with hashed passwords (see below)
4. Create your email directories in the `data/` folder
5. Access the application via your web browser

## Setting Up Users

### Generate Password Hash

You can generate a password hash using PHP from the command line:

```bash
php -r "echo password_hash('your_password_here', PASSWORD_DEFAULT) . PHP_EOL;"
```

**Example:**
```bash
php -r "echo password_hash('admin123', PASSWORD_DEFAULT) . PHP_EOL;"
```

This will output something like:
```
$2y$10$abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRS
```

### Add Users to auth.php

1. Open `auth.php`
2. Add your username and hashed password to the `$users` array:

```php
$users = [
    'admin' => '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRS',
    'john' => '$2y$10$another_hash_here',
];
```

**Example:**
```php
$users = [
    'admin' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password: password
    'user1' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // password: secret123
];
```

## Directory Structure

Organize your `.eml` files in subdirectories under `data/`:

```
data/
├── dir_1/
│   ├── notes.txt
│   ├── email1.eml
│   ├── email2.eml
│   └── email3.eml
├── dir_2/
│   ├── notes.txt
│   ├── message1.eml
│   └── message2.eml
└── project_alpha/
    ├── notes.txt
    └── correspondence.eml
```

### notes.txt Format

Each directory should contain a `notes.txt` file with a description. The **first line** will be used as the directory label in the dropdown.

**Example `notes.txt`:**
```
Customer Support Emails - March 2024
This folder contains all customer support correspondence from March 2024.
Includes resolved tickets and pending issues.
```

In the directory dropdown, this will appear as: **"Customer Support Emails - March 2024"**

If `notes.txt` is not present or empty, the directory name will be used instead.

## Usage

1. Navigate to the application in your web browser
2. Log in with your username and password
3. Select a directory from the dropdown
4. Browse the list of emails on the left
5. Click an email to view its content in the preview pane
6. Use the "← Back to Directories" button to choose a different directory
7. Click "Logout" when finished

## Security Features

- **Password Hashing**: Passwords are hashed using PHP's `password_hash()` with bcrypt
- **Session Timeout**: Sessions expire after 6 hours of inactivity
- **Path Traversal Protection**: Directory and file names are validated to prevent unauthorized access
- **XSS Prevention**: All output is properly escaped
- **Authentication Required**: All API endpoints check for valid session

## Technical Details

- **Email Parsing**: Handles MIME-encoded headers, quoted-printable, and base64 encoding
- **Multipart Support**: Extracts text/plain content from multipart emails
- **No Dependencies**: Pure PHP, no external libraries required
- **AJAX Loading**: Smooth inline updates with loading indicators

## File Structure

```
/
├── index.php          Main application UI
├── auth.php           Authentication and user credentials
├── api.php            AJAX API endpoints
├── logout.php         Session cleanup
├── README.md          This file
└── data/              Email directories
    └── <subdirs>/     Your email subdirectories
        ├── notes.txt  Directory description
        └── *.eml      Email files
```

## Troubleshooting

**Login not working:**
- Ensure your password hash was generated correctly
- Check that the username exactly matches (case-sensitive)
- Verify `auth.php` has the correct array syntax

**Emails not showing:**
- Ensure `.eml` files are in the correct directory under `data/`
- Check file permissions (web server must be able to read files)
- Verify filenames contain only alphanumeric characters, underscore, or hyphen

**Session expires too quickly:**
- Check server's session configuration
- Verify system time is correct

**Cannot see directories:**
- Ensure `data/` folder exists and is readable
- Check directory permissions

## License

Free to use and modify as needed.
