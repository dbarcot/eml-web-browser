<?php
/**
 * Authentication credentials
 *
 * Store username => hashed_password pairs
 * Use password_hash() to generate password hashes
 *
 * Example to generate a hash:
 * php -r "echo password_hash('your_password', PASSWORD_DEFAULT) . PHP_EOL;"
 */

$users = [
    // Example: 'admin' => '$2y$10$...' (hashed password)
    // Add your users below:

];

/**
 * Verify login credentials
 */
function verify_login($username, $password) {
    global $users;

    if (!isset($users[$username])) {
        return false;
    }

    return password_verify($password, $users[$username]);
}

/**
 * Check if user is authenticated
 * Session timeout: 6 hours
 */
function is_authenticated() {
    session_start();

    if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
        return false;
    }

    // Check session timeout (6 hours = 21600 seconds)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 21600)) {
        session_unset();
        session_destroy();
        return false;
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();

    return true;
}
