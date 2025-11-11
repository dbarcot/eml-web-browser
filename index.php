<?php
require_once 'auth.php';

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (verify_login($username, $password)) {
        session_start();
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['last_activity'] = time();
        header('Location: index.php');
        exit;
    } else {
        $login_error = 'Invalid username or password';
    }
}

// Check authentication
if (!is_authenticated()) {
    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>EML Viewer - Login</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .login-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                width: 100%;
                max-width: 400px;
            }

            h1 {
                text-align: center;
                color: #333;
                margin-bottom: 30px;
                font-size: 28px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            label {
                display: block;
                margin-bottom: 5px;
                color: #555;
                font-weight: 500;
            }

            input[type="text"],
            input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 5px;
                font-size: 14px;
                transition: border-color 0.3s;
            }

            input[type="text"]:focus,
            input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
            }

            button {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
            }

            button:hover {
                transform: translateY(-2px);
            }

            .error {
                background: #fee;
                color: #c33;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 20px;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1>EML Viewer</h1>
            <?php if (isset($login_error)): ?>
                <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" name="login">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EML Viewer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 24px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            font-size: 14px;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Directory Selector */
        .directory-selector {
            background: white;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .directory-selector label {
            font-weight: 600;
            margin-right: 10px;
            color: #333;
        }

        .directory-selector select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            min-width: 300px;
            cursor: pointer;
        }

        .back-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 15px;
        }

        .back-btn:hover {
            background: #5568d3;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        /* Email List */
        .email-list {
            width: 400px;
            background: white;
            border-right: 1px solid #ddd;
            overflow-y: auto;
            display: none;
        }

        .email-list.visible {
            display: block;
        }

        .email-list-header {
            padding: 15px 20px;
            background: #f9f9f9;
            border-bottom: 1px solid #ddd;
            font-weight: 600;
            color: #333;
        }

        .email-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }

        .email-item:hover {
            background: #f9f9f9;
        }

        .email-item.active {
            background: #e8eaf6;
            border-left: 3px solid #667eea;
        }

        .email-sender {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .email-subject {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .email-date {
            color: #999;
            font-size: 12px;
        }

        /* Email Preview */
        .email-preview {
            flex: 1;
            background: white;
            overflow-y: auto;
            display: none;
        }

        .email-preview.visible {
            display: block;
        }

        .email-preview-header {
            padding: 20px;
            border-bottom: 2px solid #eee;
        }

        .email-preview-subject {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }

        .email-preview-meta {
            font-size: 14px;
            color: #666;
        }

        .email-preview-meta div {
            margin-bottom: 8px;
        }

        .email-preview-meta strong {
            display: inline-block;
            width: 80px;
            color: #333;
        }

        .email-preview-body {
            padding: 20px;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: monospace;
            line-height: 1.6;
            color: #333;
        }

        /* Placeholder */
        .placeholder {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 16px;
        }

        /* Loading Indicator */
        .loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px 40px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            z-index: 1000;
        }

        .loading.visible {
            display: block;
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            text-align: center;
            color: #666;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 999;
        }

        .overlay.visible {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>EML Viewer</h1>
        <div class="header-right">
            <div class="user-info">Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?></div>
            <button class="logout-btn" onclick="logout()">Logout</button>
        </div>
    </div>

    <!-- Directory Selector -->
    <div class="directory-selector">
        <button class="back-btn" id="backBtn" style="display:none;" onclick="backToDirectories()">← Back to Directories</button>
        <label for="directorySelect">Select Email Directory:</label>
        <select id="directorySelect" onchange="loadEmails()">
            <option value="">-- Choose a directory --</option>
        </select>
        <span id="currentDirectory" style="display:none; margin-left: 15px; font-weight: 600; color: #667eea;"></span>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Email List -->
        <div class="email-list" id="emailList">
            <div class="email-list-header">Emails</div>
            <div id="emailItems"></div>
        </div>

        <!-- Email Preview -->
        <div class="email-preview" id="emailPreview">
            <div class="email-preview-header">
                <div class="email-preview-subject" id="previewSubject"></div>
                <div class="email-preview-meta" id="previewMeta"></div>
            </div>
            <div class="email-preview-body" id="previewBody"></div>
        </div>

        <!-- Placeholder -->
        <div class="placeholder" id="placeholder">
            Select a directory to view emails
        </div>
    </div>

    <!-- Loading Indicator -->
    <div class="overlay" id="overlay"></div>
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading...</div>
    </div>

    <script>
        let currentDirectory = '';
        let emails = [];

        // Load directories on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadDirectories();
        });

        // Show/hide loading indicator
        function showLoading() {
            document.getElementById('overlay').classList.add('visible');
            document.getElementById('loading').classList.add('visible');
        }

        function hideLoading() {
            document.getElementById('overlay').classList.remove('visible');
            document.getElementById('loading').classList.remove('visible');
        }

        // Load directories
        function loadDirectories() {
            showLoading();
            fetch('api.php?action=get_dirs')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('directorySelect');
                    select.innerHTML = '<option value="">-- Choose a directory --</option>';

                    data.forEach(dir => {
                        const option = document.createElement('option');
                        option.value = dir.name;
                        option.textContent = dir.label;
                        select.appendChild(option);
                    });

                    hideLoading();
                })
                .catch(error => {
                    console.error('Error loading directories:', error);
                    hideLoading();
                    alert('Error loading directories');
                });
        }

        // Load emails from selected directory
        function loadEmails() {
            const select = document.getElementById('directorySelect');
            const directory = select.value;

            if (!directory) {
                document.getElementById('emailList').classList.remove('visible');
                document.getElementById('emailPreview').classList.remove('visible');
                document.getElementById('placeholder').style.display = 'flex';
                document.getElementById('backBtn').style.display = 'none';
                document.getElementById('currentDirectory').style.display = 'none';
                select.style.display = 'inline-block';
                return;
            }

            currentDirectory = directory;

            showLoading();
            fetch(`api.php?action=get_emails&dir=${encodeURIComponent(directory)}`)
                .then(response => response.json())
                .then(data => {
                    emails = data.emails;

                    // Update UI
                    select.style.display = 'none';
                    document.getElementById('backBtn').style.display = 'inline-block';
                    document.getElementById('currentDirectory').style.display = 'inline';
                    document.getElementById('currentDirectory').textContent = data.directory_label;

                    // Show email list
                    document.getElementById('placeholder').style.display = 'none';
                    document.getElementById('emailList').classList.add('visible');

                    // Populate email list
                    const emailItems = document.getElementById('emailItems');
                    emailItems.innerHTML = '';

                    emails.forEach((email, index) => {
                        const item = document.createElement('div');
                        item.className = 'email-item';
                        item.onclick = () => viewEmail(index);

                        item.innerHTML = `
                            <div class="email-sender">${escapeHtml(email.from)}</div>
                            <div class="email-subject">${escapeHtml(email.subject)}</div>
                            <div class="email-date">${escapeHtml(email.date)}</div>
                        `;

                        emailItems.appendChild(item);
                    });

                    hideLoading();
                })
                .catch(error => {
                    console.error('Error loading emails:', error);
                    hideLoading();
                    alert('Error loading emails');
                });
        }

        // View email content
        function viewEmail(index) {
            const email = emails[index];

            // Update active state
            document.querySelectorAll('.email-item').forEach((item, i) => {
                item.classList.toggle('active', i === index);
            });

            showLoading();
            fetch(`api.php?action=get_email_content&dir=${encodeURIComponent(currentDirectory)}&file=${encodeURIComponent(email.filename)}`)
                .then(response => response.json())
                .then(data => {
                    // Show preview
                    document.getElementById('emailPreview').classList.add('visible');

                    // Set subject
                    document.getElementById('previewSubject').textContent = data.subject;

                    // Set metadata
                    let metaHtml = `<div><strong>From:</strong> ${escapeHtml(data.from)}</div>`;
                    metaHtml += `<div><strong>Date:</strong> ${escapeHtml(data.date)}</div>`;
                    if (data.to) metaHtml += `<div><strong>To:</strong> ${escapeHtml(data.to)}</div>`;
                    if (data.cc) metaHtml += `<div><strong>CC:</strong> ${escapeHtml(data.cc)}</div>`;
                    if (data.bcc) metaHtml += `<div><strong>BCC:</strong> ${escapeHtml(data.bcc)}</div>`;
                    document.getElementById('previewMeta').innerHTML = metaHtml;

                    // Set body
                    document.getElementById('previewBody').textContent = data.body;

                    hideLoading();
                })
                .catch(error => {
                    console.error('Error loading email content:', error);
                    hideLoading();
                    alert('Error loading email content');
                });
        }

        // Back to directories
        function backToDirectories() {
            document.getElementById('directorySelect').value = '';
            document.getElementById('directorySelect').style.display = 'inline-block';
            document.getElementById('backBtn').style.display = 'none';
            document.getElementById('currentDirectory').style.display = 'none';
            document.getElementById('emailList').classList.remove('visible');
            document.getElementById('emailPreview').classList.remove('visible');
            document.getElementById('placeholder').style.display = 'flex';
            currentDirectory = '';
            emails = [];
        }

        // Logout
        function logout() {
            window.location.href = 'logout.php';
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
