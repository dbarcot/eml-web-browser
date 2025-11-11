<?php
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

    $email = parseEmail($filePath);

    echo json_encode($email);
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
    // Must end with .eml and contain only safe characters
    return preg_match('/^[a-zA-Z0-9_-]+\.eml$/', $name) === 1;
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
    $content = file_get_contents($filePath);
    $headers = [];
    $body = '';

    // Split headers and body
    $parts = preg_split('/\r?\n\r?\n/', $content, 2);
    $headerLines = $parts[0];
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

    // Check if multipart
    if (stripos($contentType, 'multipart') !== false) {
        // Extract boundary
        if (preg_match('/boundary="?([^";\s]+)"?/i', $contentType, $matches)) {
            $boundary = $matches[1];
            $body = parseMultipartBody($bodyContent, $boundary);
        }
    } else {
        // Single part - decode based on transfer encoding
        $body = decodeBody($bodyContent, $transferEncoding);
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
    $parts = preg_split('/--' . preg_quote($boundary, '/') . '/', $content);

    foreach ($parts as $part) {
        if (empty(trim($part)) || strpos($part, '--') === 0) {
            continue;
        }

        // Split part headers and content
        $partParts = preg_split('/\r?\n\r?\n/', $part, 2);
        if (count($partParts) < 2) {
            continue;
        }

        $partHeaders = $partParts[0];
        $partContent = $partParts[1];

        // Check if this is text/plain
        if (stripos($partHeaders, 'text/plain') !== false) {
            // Get transfer encoding from part headers
            $transferEncoding = '';
            if (preg_match('/Content-Transfer-Encoding:\s*(.+)/i', $partHeaders, $matches)) {
                $transferEncoding = trim($matches[1]);
            }

            return decodeBody($partContent, $transferEncoding);
        }
    }

    // If no text/plain part found, try text/html and strip tags
    foreach ($parts as $part) {
        if (empty(trim($part)) || strpos($part, '--') === 0) {
            continue;
        }

        $partParts = preg_split('/\r?\n\r?\n/', $part, 2);
        if (count($partParts) < 2) {
            continue;
        }

        $partHeaders = $partParts[0];
        $partContent = $partParts[1];

        if (stripos($partHeaders, 'text/html') !== false) {
            $transferEncoding = '';
            if (preg_match('/Content-Transfer-Encoding:\s*(.+)/i', $partHeaders, $matches)) {
                $transferEncoding = trim($matches[1]);
            }

            $decoded = decodeBody($partContent, $transferEncoding);
            return strip_tags($decoded);
        }
    }

    return 'No text content found';
}

/**
 * Decode email body based on transfer encoding
 */
function decodeBody($content, $encoding) {
    $encoding = strtolower(trim($encoding));

    switch ($encoding) {
        case 'quoted-printable':
            return quoted_printable_decode($content);

        case 'base64':
            return base64_decode($content);

        case '7bit':
        case '8bit':
        case 'binary':
        default:
            return $content;
    }
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
