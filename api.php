<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output (breaks JSON)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("FATAL ERROR: " . print_r($error, true));
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'error' => 'Fatal error',
                'message' => $error['message'],
                'file' => basename($error['file']),
                'line' => $error['line']
            ]);
        }
    }
});

// Start output buffering to catch any unexpected output
ob_start();

require_once 'auth.php';

// Check authentication
if (!is_authenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_dirs':
        getDirs();
        break;

    case 'get_emails':
        getEmails();
        break;

    case 'get_email_content':
        getEmailContent();
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

/**
 * Get list of directories in data/
 */
function getDirs() {
    $dataDir = __DIR__ . '/data';
    $dirs = [];

    if (!is_dir($dataDir)) {
        echo json_encode([]);
        return;
    }

    $items = scandir($dataDir);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dataDir . '/' . $item;

        if (is_dir($path)) {
            // Check for notes.txt
            $notesFile = $path . '/notes.txt';
            $label = $item;

            if (file_exists($notesFile)) {
                $notes = file_get_contents($notesFile);
                $notes = trim($notes);
                if (!empty($notes)) {
                    // Use first line as label
                    $lines = explode("\n", $notes);
                    $label = trim($lines[0]);
                }
            }

            $dirs[] = [
                'name' => $item,
                'label' => $label
            ];
        }
    }

    echo json_encode($dirs);
}

/**
 * Get list of emails from a directory
 */
function getEmails() {
    $dir = $_GET['dir'] ?? '';

    // Validate directory name (prevent path traversal)
    if (!isValidDirectoryName($dir)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid directory']);
        return;
    }

    $dirPath = __DIR__ . '/data/' . $dir;

    if (!is_dir($dirPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Directory not found']);
        return;
    }

    // Get directory label
    $label = $dir;
    $notesFile = $dirPath . '/notes.txt';
    if (file_exists($notesFile)) {
        $notes = file_get_contents($notesFile);
        $notes = trim($notes);
        if (!empty($notes)) {
            $lines = explode("\n", $notes);
            $label = trim($lines[0]);
        }
    }

    // Get .eml files
    $emails = [];
    $files = scandir($dirPath);

    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'eml') {
            $filePath = $dirPath . '/' . $file;
            $headers = parseEmailHeaders($filePath);

            $emails[] = [
                'filename' => $file,
                'from' => $headers['from'] ?? 'Unknown',
                'subject' => $headers['subject'] ?? 'No Subject',
                'date' => $headers['date'] ?? 'Unknown Date'
            ];
        }
    }

    // Sort by date (newest first)
    usort($emails, function($a, $b) {
        $dateA = strtotime($a['date']);
        $dateB = strtotime($b['date']);
        return $dateB - $dateA;
    });

    echo json_encode([
        'directory_label' => $label,
        'emails' => $emails
    ]);
}

/**
 * Get email content
 */
function getEmailContent() {
    $dir = $_GET['dir'] ?? '';
    $file = $_GET['file'] ?? '';

    // Validate directory and file names
    if (!isValidDirectoryName($dir) || !isValidFileName($file)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid parameters']);
        return;
    }

    $filePath = __DIR__ . '/data/' . $dir . '/' . $file;

    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Email not found']);
        return;
    }

    try {
        $email = parseEmail($filePath);

        // Ensure valid UTF-8
        array_walk_recursive($email, function(&$item) {
            if (is_string($item)) {
                $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
            }
        });

        $json = json_encode($email);
        if ($json === false) {
            throw new Exception('JSON encoding failed: ' . json_last_error_msg());
        }

        echo $json;
        ob_end_flush();
    } catch (Exception $e) {
        error_log("Error parsing email $filePath: " . $e->getMessage());
        ob_clean(); // Clean any output
        http_response_code(500);
        echo json_encode([
            'error' => 'Error parsing email',
            'message' => $e->getMessage(),
            'file' => $file
        ]);
        ob_end_flush();
    }
}

/**
 * Validate directory name (prevent path traversal)
 */
function isValidDirectoryName($name) {
    // Only allow alphanumeric, underscore, hyphen
    return preg_match('/^[a-zA-Z0-9_-]+$/', $name) === 1;
}

/**
 * Validate file name
 */
function isValidFileName($name) {
    // Must end with .eml
    if (!preg_match('/\.eml$/i', $name)) {
        return false;
    }

    // Block path traversal attempts
    if (strpos($name, '..') !== false || strpos($name, '/') !== false || strpos($name, '\\') !== false) {
        return false;
    }

    // Block null bytes
    if (strpos($name, "\0") !== false) {
        return false;
    }

    // Must not start with a dot (hidden files)
    if (strpos($name, '.') === 0) {
        return false;
    }

    return true;
}

/**
 * Parse email headers only
 */
function parseEmailHeaders($filePath) {
    $content = file_get_contents($filePath);
    $headers = [];

    // Split headers and body
    $parts = preg_split('/\r?\n\r?\n/', $content, 2);
    $headerLines = $parts[0];

    // Parse headers
    $lines = preg_split('/\r?\n/', $headerLines);
    $currentHeader = '';

    foreach ($lines as $line) {
        // Check if line is a continuation (starts with space or tab)
        if (preg_match('/^\s+/', $line) && $currentHeader) {
            $headers[$currentHeader] .= ' ' . trim($line);
        } else {
            // New header
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                $headerName = strtolower(trim($matches[1]));
                $headerValue = trim($matches[2]);
                $currentHeader = $headerName;
                $headers[$headerName] = $headerValue;
            }
        }
    }

    // Decode headers
    foreach ($headers as $key => $value) {
        $headers[$key] = decodeHeader($value);
    }

    return $headers;
}

/**
 * Parse full email content
 */
function parseEmail($filePath) {
    // Check file size (limit to 10MB to prevent memory issues)
    $fileSize = filesize($filePath);
    if ($fileSize > 10 * 1024 * 1024) {
        throw new Exception("Email file too large: " . round($fileSize / 1024 / 1024, 2) . "MB");
    }

    $content = @file_get_contents($filePath);
    if ($content === false) {
        throw new Exception("Unable to read email file");
    }

    if (empty($content)) {
        throw new Exception("Email file is empty");
    }

    $headers = [];
    $body = '';

    // Split headers and body
    $parts = @preg_split('/\r?\n\r?\n/', $content, 2);
    if ($parts === false) {
        throw new Exception("Failed to parse email structure");
    }

    $headerLines = $parts[0] ?? '';
    $bodyContent = $parts[1] ?? '';

    // Parse headers
    $lines = preg_split('/\r?\n/', $headerLines);
    $currentHeader = '';

    foreach ($lines as $line) {
        if (preg_match('/^\s+/', $line) && $currentHeader) {
            $headers[$currentHeader] .= ' ' . trim($line);
        } else {
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                $headerName = strtolower(trim($matches[1]));
                $headerValue = trim($matches[2]);
                $currentHeader = $headerName;
                $headers[$headerName] = $headerValue;
            }
        }
    }

    // Decode headers
    foreach ($headers as $key => $value) {
        $headers[$key] = decodeHeader($value);
    }

    // Parse body
    $contentType = $headers['content-type'] ?? '';
    $transferEncoding = $headers['content-transfer-encoding'] ?? '';
    $charset = extractCharset($contentType);

    // Check if multipart
    if (stripos($contentType, 'multipart') !== false) {
        // Extract boundary
        if (preg_match('/boundary="?([^";\s]+)"?/i', $contentType, $matches)) {
            $boundary = $matches[1];
            try {
                $body = parseMultipartBody($bodyContent, $boundary);
            } catch (Exception $e) {
                error_log("Error parsing multipart body: " . $e->getMessage());
                $body = "Error parsing multipart email: " . $e->getMessage();
            }
        } else {
            $body = "Multipart email but no boundary found";
        }
    } else {
        // Single part - decode based on transfer encoding
        try {
            $body = decodeBody($bodyContent, $transferEncoding, $charset);

            // If it's HTML, convert to plain text
            if (stripos($contentType, 'text/html') !== false) {
                $body = htmlToText($body);
            }
        } catch (Exception $e) {
            error_log("Error decoding body: " . $e->getMessage());
            $body = "Error decoding email body: " . $e->getMessage();
        }
    }

    return [
        'subject' => $headers['subject'] ?? 'No Subject',
        'from' => $headers['from'] ?? 'Unknown',
        'to' => $headers['to'] ?? '',
        'cc' => $headers['cc'] ?? '',
        'bcc' => $headers['bcc'] ?? '',
        'date' => $headers['date'] ?? 'Unknown Date',
        'body' => $body
    ];
}

/**
 * Parse multipart email body (extract text/plain part)
 */
function parseMultipartBody($content, $boundary) {
    // Split by boundary
    $parts = preg_split('/--' . preg_quote($boundary, '/') . '(?:--)?[\r\n]*/', $content);

    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) {
            continue;
        }

        // Split part into headers and content
        // Look for double line break (CRLF CRLF, LF LF, or CR CR)
        if (preg_match('/^(.*?)(?:\r\n\r\n|\n\n|\r\r)(.*)$/s', $part, $matches)) {
            $partHeaders = $matches[1];
            $partContent = $matches[2];
        } else {
            continue;
        }

        // Parse part headers into array
        $headers = parsePartHeaders($partHeaders);

        // Check if this is text/plain
        if (isset($headers['content-type']) && stripos($headers['content-type'], 'text/plain') !== false) {
            $transferEncoding = $headers['content-transfer-encoding'] ?? '';
            $charset = extractCharset($headers['content-type']);
            return decodeBody($partContent, $transferEncoding, $charset);
        }
    }

    // If no text/plain part found, try text/html and strip tags
    $parts = preg_split('/--' . preg_quote($boundary, '/') . '(?:--)?[\r\n]*/', $content);

    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) {
            continue;
        }

        if (preg_match('/^(.*?)(?:\r\n\r\n|\n\n|\r\r)(.*)$/s', $part, $matches)) {
            $partHeaders = $matches[1];
            $partContent = $matches[2];
        } else {
            continue;
        }

        $headers = parsePartHeaders($partHeaders);

        if (isset($headers['content-type']) && stripos($headers['content-type'], 'text/html') !== false) {
            $transferEncoding = $headers['content-transfer-encoding'] ?? '';
            $charset = extractCharset($headers['content-type']);
            $decoded = decodeBody($partContent, $transferEncoding, $charset);
            return htmlToText($decoded);
        }
    }

    return 'No text content found';
}

/**
 * Parse MIME part headers
 */
function parsePartHeaders($headerText) {
    $headers = [];
    $lines = preg_split('/\r\n|\n|\r/', $headerText);
    $currentHeader = '';
    $currentValue = '';

    foreach ($lines as $line) {
        // Check if line is a continuation (starts with space or tab)
        if (preg_match('/^[\s\t]+/', $line)) {
            if ($currentHeader) {
                $currentValue .= ' ' . trim($line);
            }
        } else {
            // Save previous header
            if ($currentHeader) {
                $headers[strtolower($currentHeader)] = $currentValue;
            }

            // Parse new header
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                $currentHeader = trim($matches[1]);
                $currentValue = trim($matches[2]);
            } else {
                $currentHeader = '';
                $currentValue = '';
            }
        }
    }

    // Save last header
    if ($currentHeader) {
        $headers[strtolower($currentHeader)] = $currentValue;
    }

    return $headers;
}

/**
 * Extract charset from Content-Type header
 */
function extractCharset($contentType) {
    if (preg_match('/charset=["\']?([^"\';\s]+)["\']?/i', $contentType, $matches)) {
        return strtoupper(trim($matches[1]));
    }
    return 'UTF-8'; // Default to UTF-8
}

/**
 * Decode email body based on transfer encoding
 */
function decodeBody($content, $encoding, $charset = 'UTF-8') {
    $encoding = strtolower(trim($encoding));

    // Decode based on transfer encoding
    switch ($encoding) {
        case 'quoted-printable':
            $decoded = quoted_printable_decode($content);
            break;

        case 'base64':
            $decoded = base64_decode($content);
            break;

        case '7bit':
        case '8bit':
        case 'binary':
        default:
            $decoded = $content;
            break;
    }

    // Convert charset to UTF-8 if needed
    $charset = strtoupper(trim($charset));
    if ($charset !== 'UTF-8' && $charset !== '') {
        $converted = @mb_convert_encoding($decoded, 'UTF-8', $charset);
        if ($converted !== false) {
            return $converted;
        } else {
            error_log("Failed to convert from $charset to UTF-8");
            // Try iconv as fallback
            $converted = @iconv($charset, 'UTF-8//IGNORE', $decoded);
            if ($converted !== false) {
                return $converted;
            }
        }
    }

    return $decoded;
}

/**
 * Convert HTML to plain text
 */
function htmlToText($html) {
    // Replace common block elements with line breaks
    $html = preg_replace('/<(br|BR)[\s\/]*>/i', "\n", $html);
    $html = preg_replace('/<\/(div|DIV|p|P|tr|TR|h[1-6]|H[1-6])>/i', "\n", $html);

    // Remove script and style tags and their content
    $html = preg_replace('/<(script|style|SCRIPT|STYLE)[^>]*>.*?<\/\1>/is', '', $html);

    // Remove all HTML tags
    $text = strip_tags($html);

    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Convert multiple spaces to single space
    $text = preg_replace('/[ \t]+/', ' ', $text);

    // Convert multiple newlines to maximum 2
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    // Trim whitespace from each line
    $lines = explode("\n", $text);
    $lines = array_map('trim', $lines);
    $text = implode("\n", $lines);

    // Remove empty lines at start and end
    $text = trim($text);

    return $text;
}

/**
 * Decode MIME encoded header
 */
function decodeHeader($header) {
    // Decode MIME encoded words (=?charset?encoding?text?=)
    $header = preg_replace_callback(
        '/=\?([^?]+)\?([QB])\?([^?]+)\?=/i',
        function($matches) {
            $charset = $matches[1];
            $encoding = strtoupper($matches[2]);
            $text = $matches[3];

            if ($encoding === 'B') {
                $decoded = base64_decode($text);
            } else if ($encoding === 'Q') {
                $decoded = quoted_printable_decode(str_replace('_', ' ', $text));
            } else {
                $decoded = $text;
            }

            // Convert to UTF-8 if needed
            if (strtoupper($charset) !== 'UTF-8') {
                $decoded = mb_convert_encoding($decoded, 'UTF-8', $charset);
            }

            return $decoded;
        },
        $header
    );

    return $header;
}
