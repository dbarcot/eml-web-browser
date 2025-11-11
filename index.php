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

        .email-selection-toolbar {
            padding: 10px 20px;
            background: #fff;
            border-bottom: 1px solid #ddd;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .selection-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
        }

        .selection-btn:hover {
            background: #5568d3;
        }

        .selection-btn.secondary {
            background: #6c757d;
        }

        .selection-btn.secondary:hover {
            background: #5a6268;
        }

        .selection-btn.primary {
            background: #ff9800;
        }

        .selection-btn.primary:hover {
            background: #e68900;
        }

        .selection-count {
            color: #666;
            font-size: 13px;
        }

        .email-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
            position: relative;
            display: flex;
            align-items: center;
        }

        .email-item:hover {
            background: #f9f9f9;
        }

        .email-item.active {
            background: #e8eaf6;
            border-left: 3px solid #667eea;
        }

        .email-item-checkbox {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            cursor: pointer;
            display: none;
        }

        .email-item-checkbox.visible {
            display: block;
        }

        .email-item-content {
            flex: 1;
        }

        .email-item.selected {
            background: #fff3e0;
            border-left: 3px solid #ff9800;
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

    <!-- jsPDF library for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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
            <div class="email-selection-toolbar" id="selectionToolbar" style="display: none;">
                <button class="selection-btn" onclick="toggleSelectMode()">Select Emails</button>
                <button class="selection-btn secondary" id="selectAllBtn" onclick="selectAll()" style="display: none;">Select All</button>
                <button class="selection-btn secondary" id="clearBtn" onclick="clearSelection()" style="display: none;">Clear</button>
                <button class="selection-btn primary" id="printBtn" onclick="printSelectedEmails()" style="display: none;">
                    Print Selected (<span id="selectedCount">0</span>)
                </button>
                <span class="selection-count" id="selectionCount" style="display: none;"></span>
            </div>
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
                        item.dataset.index = index;

                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.className = 'email-item-checkbox';
                        checkbox.onclick = (e) => {
                            e.stopPropagation();
                            toggleEmailSelection(index);
                        };

                        const content = document.createElement('div');
                        content.className = 'email-item-content';
                        content.onclick = () => viewEmail(index);
                        content.innerHTML = `
                            <div class="email-sender">${escapeHtml(email.from)}</div>
                            <div class="email-subject">${escapeHtml(email.subject)}</div>
                            <div class="email-date">${escapeHtml(email.date)}</div>
                        `;

                        item.appendChild(checkbox);
                        item.appendChild(content);
                        emailItems.appendChild(item);
                    });

                    // Show selection toolbar
                    document.getElementById('selectionToolbar').style.display = 'flex';

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
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`HTTP ${response.status}: ${text}`);
                        });
                    }
                    return response.text().then(text => {
                        if (!text || text.trim() === '') {
                            throw new Error('Empty response from server');
                        }
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Invalid JSON response:', text);
                            throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                        }
                    });
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.message || data.error);
                    }

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
                    alert('Error loading email: ' + error.message);
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

        // Selection mode state
        let selectMode = false;
        let selectedEmails = new Set();

        // Toggle select mode
        function toggleSelectMode() {
            selectMode = !selectMode;
            const checkboxes = document.querySelectorAll('.email-item-checkbox');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const clearBtn = document.getElementById('clearBtn');

            checkboxes.forEach(cb => {
                if (selectMode) {
                    cb.classList.add('visible');
                } else {
                    cb.classList.remove('visible');
                    cb.checked = false;
                }
            });

            if (selectMode) {
                selectAllBtn.style.display = 'inline-block';
                clearBtn.style.display = 'inline-block';
                clearSelection();
            } else {
                selectAllBtn.style.display = 'none';
                clearBtn.style.display = 'none';
                document.getElementById('printBtn').style.display = 'none';
                selectedEmails.clear();
            }
        }

        // Toggle email selection
        function toggleEmailSelection(index) {
            const item = document.querySelector(`.email-item[data-index="${index}"]`);
            const checkbox = item.querySelector('.email-item-checkbox');

            if (checkbox.checked) {
                selectedEmails.add(index);
                item.classList.add('selected');
            } else {
                selectedEmails.delete(index);
                item.classList.remove('selected');
            }

            updateSelectionCount();
        }

        // Select all emails
        function selectAll() {
            selectedEmails.clear();
            const checkboxes = document.querySelectorAll('.email-item-checkbox');
            checkboxes.forEach((cb, index) => {
                cb.checked = true;
                selectedEmails.add(index);
                const item = document.querySelector(`.email-item[data-index="${index}"]`);
                if (item) item.classList.add('selected');
            });
            updateSelectionCount();
        }

        // Clear selection
        function clearSelection() {
            selectedEmails.clear();
            const checkboxes = document.querySelectorAll('.email-item-checkbox');
            checkboxes.forEach((cb, index) => {
                cb.checked = false;
                const item = document.querySelector(`.email-item[data-index="${index}"]`);
                if (item) item.classList.remove('selected');
            });
            updateSelectionCount();
        }

        // Update selection count
        function updateSelectionCount() {
            const count = selectedEmails.size;
            const printBtn = document.getElementById('printBtn');
            const selectedCountSpan = document.getElementById('selectedCount');

            selectedCountSpan.textContent = count;

            if (count > 0) {
                printBtn.style.display = 'inline-block';
            } else {
                printBtn.style.display = 'none';
            }
        }

        // Print selected emails to PDF
        async function printSelectedEmails() {
            if (selectedEmails.size === 0) {
                alert('Please select at least one email');
                return;
            }

            showLoading();

            try {
                // Load jsPDF
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                // Fetch all selected emails
                const emailPromises = Array.from(selectedEmails).sort((a, b) => a - b).map(index =>
                    fetch(`api.php?action=get_email_content&dir=${encodeURIComponent(currentDirectory)}&file=${encodeURIComponent(emails[index].filename)}`)
                        .then(response => response.json())
                );

                const emailsData = await Promise.all(emailPromises);

                // Get directory label
                const dirLabel = document.getElementById('currentDirectory').textContent || currentDirectory;

                // Generate PDF
                let pageCount = 0;

                for (let i = 0; i < emailsData.length; i++) {
                    if (i > 0) {
                        doc.addPage();
                    }
                    pageCount++;

                    const emailData = emailsData[i];
                    let yPos = 20;

                    // Header separator
                    doc.setFontSize(10);
                    doc.setFont('helvetica', 'normal');
                    doc.text('═'.repeat(80), 15, yPos);
                    yPos += 7;

                    // Email number
                    doc.setFontSize(12);
                    doc.setFont('helvetica', 'bold');
                    doc.text(`EMAIL ${i + 1} of ${emailsData.length}`, 15, yPos);
                    yPos += 7;

                    // Separator
                    doc.setFontSize(10);
                    doc.setFont('helvetica', 'normal');
                    doc.text('═'.repeat(80), 15, yPos);
                    yPos += 10;

                    // Email headers
                    doc.setFontSize(11);
                    doc.setFont('helvetica', 'bold');

                    // From
                    doc.text('From:', 15, yPos);
                    doc.setFont('helvetica', 'normal');
                    doc.text(emailData.from, 40, yPos, { maxWidth: 155 });
                    yPos += 7;

                    // To
                    if (emailData.to) {
                        doc.setFont('helvetica', 'bold');
                        doc.text('To:', 15, yPos);
                        doc.setFont('helvetica', 'normal');
                        doc.text(emailData.to, 40, yPos, { maxWidth: 155 });
                        yPos += 7;
                    }

                    // CC
                    if (emailData.cc) {
                        doc.setFont('helvetica', 'bold');
                        doc.text('CC:', 15, yPos);
                        doc.setFont('helvetica', 'normal');
                        doc.text(emailData.cc, 40, yPos, { maxWidth: 155 });
                        yPos += 7;
                    }

                    // Date
                    doc.setFont('helvetica', 'bold');
                    doc.text('Date:', 15, yPos);
                    doc.setFont('helvetica', 'normal');
                    doc.text(emailData.date, 40, yPos, { maxWidth: 155 });
                    yPos += 7;

                    // Subject
                    doc.setFont('helvetica', 'bold');
                    doc.text('Subject:', 15, yPos);
                    doc.setFont('helvetica', 'normal');
                    const subjectLines = doc.splitTextToSize(emailData.subject, 155);
                    doc.text(subjectLines, 40, yPos);
                    yPos += 7 * subjectLines.length;

                    yPos += 5;

                    // Separator
                    doc.setFontSize(10);
                    doc.text('═'.repeat(80), 15, yPos);
                    yPos += 10;

                    // Email body
                    doc.setFontSize(9);
                    doc.setFont('courier', 'normal');
                    const bodyLines = doc.splitTextToSize(emailData.body || '(No content)', 180);

                    for (let line of bodyLines) {
                        if (yPos > 270) {
                            doc.addPage();
                            yPos = 20;
                        }
                        doc.text(line, 15, yPos);
                        yPos += 5;
                    }

                    // Footer
                    doc.setFontSize(8);
                    doc.setFont('helvetica', 'normal');
                    doc.setTextColor(100);
                    yPos = 280;
                    doc.text('─'.repeat(90), 15, yPos);
                    yPos += 5;
                    doc.text(`Source: ${dirLabel}`, 15, yPos);
                    const attachmentText = emailData.attachments > 0
                        ? `${emailData.attachments} attachment${emailData.attachments > 1 ? 's' : ''} detected`
                        : 'No attachments';
                    doc.text(`Attachments: ${attachmentText}`, 15, yPos + 4);
                    doc.text(`Generated: ${new Date().toLocaleString()}`, 15, yPos + 8);
                    doc.setTextColor(0);
                }

                // Generate filename
                const timestamp = new Date().toISOString().split('T')[0];
                const filename = emailsData.length === 1
                    ? `Email - ${emailsData[0].subject.substring(0, 50)} - ${timestamp}.pdf`
                    : `Emails - ${dirLabel} - ${emailsData.length} emails - ${timestamp}.pdf`;

                // Save PDF
                doc.save(filename);

                hideLoading();
                alert(`PDF generated successfully!\n${emailsData.length} email${emailsData.length > 1 ? 's' : ''} exported.`);

            } catch (error) {
                console.error('Error generating PDF:', error);
                hideLoading();
                alert('Error generating PDF: ' + error.message);
            }
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
