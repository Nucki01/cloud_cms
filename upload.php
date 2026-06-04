<?php
/**
 * upload.php
 * Lets logged-in users upload a file.
 * Saves the file to /uploads/ and inserts a record into the database.
 * Also links the file to one or more categories (N:N relationship).
 */

require_once 'Database.php';

session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId       = $_SESSION['user_id'];
$userName     = $_SESSION['user_name'];
$userRole     = $_SESSION['user_role'];
$avatarLetter = strtoupper($userName[0]);

$db      = Database::getInstance();
$error   = '';
$success = '';

// Load all categories for the checkbox list
$categories = $db->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

// ── Handle the upload form submission ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check a file was actually selected
    if (empty($_FILES['upload_file']['name'])) {
        $error = 'Please choose a file to upload.';

    } elseif ($_FILES['upload_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Please try again.';

    } else {
        $originalName = basename($_FILES['upload_file']['name']);
        $fileSize     = $_FILES['upload_file']['size'];
        $tmpPath      = $_FILES['upload_file']['tmp_name'];

        // Max file size: 5 MB
        if ($fileSize > 5 * 1024 * 1024) {
            $error = 'File is too large. Maximum size is 5 MB.';

        } else {
            // Build a safe, unique file name to avoid collisions on disk
            $ext        = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $storedName = uniqid('file_', true) . '.' . $ext;
            $uploadDir  = __DIR__ . '/uploads/';
            $filePath   = 'uploads/' . $storedName;

            // Move the uploaded file from PHP's temp folder to our uploads folder
            if (move_uploaded_file($tmpPath, $uploadDir . $storedName)) {

                // Insert a record into the files table
                $stmt = $db->prepare(
                    'INSERT INTO files (user_id, original_name, stored_name, file_path, file_size)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $originalName, $storedName, $filePath, $fileSize]);

                // Get the new file's auto-generated ID
                $newFileId = $db->lastInsertId();

                // Link selected categories (N:N via file_categories table)
                $selectedCats = $_POST['categories'] ?? [];
                if (!empty($selectedCats)) {
                    $linkStmt = $db->prepare(
                        'INSERT INTO file_categories (file_id, category_id) VALUES (?, ?)'
                    );
                    foreach ($selectedCats as $catId) {
                        $linkStmt->execute([$newFileId, (int)$catId]);
                    }
                }

                $success = 'File "' . htmlspecialchars($originalName) . '" uploaded successfully!';

            } else {
                $error = 'Could not save the file. Check that the uploads/ folder exists and is writable.';
            }
        }
    }
}

// ── Load this user's uploaded files to show in the table ───
if ($userRole === 'admin') {
    $filesStmt = $db->query(
        'SELECT f.id, f.original_name, f.file_size, f.uploaded_at, u.name AS uploader
         FROM files f JOIN users u ON f.user_id = u.id
         ORDER BY f.uploaded_at DESC'
    );
} else {
    $filesStmt = $db->prepare(
        'SELECT f.id, f.original_name, f.file_size, f.uploaded_at, u.name AS uploader
         FROM files f JOIN users u ON f.user_id = u.id
         WHERE f.user_id = ?
         ORDER BY f.uploaded_at DESC'
    );
    $filesStmt->execute([$userId]);
}
$myFiles = $filesStmt->fetchAll();

// Helper: readable file size
function formatSize(int $bytes): string {
    if ($bytes < 1024)    return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File – CloudCMS</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<div class="sidebar-overlay" id="overlay"></div>

<div class="mobile-topbar">
    <div class="mobile-brand">
        <div class="brand-icon">☁</div>
        CloudCMS
    </div>
    <button class="hamburger" id="hamburger" aria-label="Open menu">
        <span></span><span></span><span></span>
    </button>
</div>

<div class="layout">

    <!-- ── Sidebar ── -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">☁</div>
            <span class="brand-name">CloudCMS</span>
        </div>
        <nav>
            <a href="dashboard.php"><span class="nav-icon">📊</span> Overview</a>
            <a href="upload.php" class="active"><span class="nav-icon">📁</span> Upload File</a>
            <?php if ($userRole === 'admin'): ?>
                <a href="admin.php"><span class="nav-icon">⚙️</span> Admin Panel</a>
            <?php endif; ?>
            <a href="profile.php"><span class="nav-icon">👤</span> Profile</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php"><span class="nav-icon">🚪</span> Log Out</a>
        </div>
    </aside>

    <!-- ── Main content ── -->
    <main class="main-content">

        <!-- Top bar -->
        <header class="topbar">
            <div class="topbar-title">
                <h1>Upload File</h1>
                <p>Upload and categorise your documents here.</p>
            </div>
            <div class="topbar-user">
                <div class="avatar"><?= htmlspecialchars($avatarLetter) ?></div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($userName) ?></strong>
                    <small><?= $userRole === 'admin' ? 'Admin account' : 'Standard user' ?></small>
                </div>
            </div>
        </header>

        <!-- Alert messages -->
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <!-- ── Upload form card ── -->
        <section class="card">
            <h2 class="card-title">Choose a file</h2>

            <!--
                enctype="multipart/form-data" is required for file uploads.
                method="post" keeps data out of the URL.
            -->
            <form action="" method="post" enctype="multipart/form-data" id="uploadForm">

                <!-- Clickable upload zone — jQuery triggers the hidden input -->
                <div class="upload-zone" id="uploadZone">
                    <div class="upload-icon">☁️</div>
                    <p><strong>Click to browse</strong> or drag and drop your file here</p>
                    <p style="font-size:0.8rem; margin-top:6px;">Max size: 5 MB</p>
                </div>

                <!-- Hidden file input — shown/triggered by clicking the zone above -->
                <input
                    type="file"
                    name="upload_file"
                    id="fileInput"
                    required
                    style="display:none;"
                >

                <!-- File name preview shown after a file is selected -->
                <p id="filePreview" class="text-muted mt-8" style="display:none;"></p>

                <hr class="divider">

                <!-- Category checkboxes (N:N relationship) -->
                <div class="form-group">
                    <label>Assign categories <span class="text-muted">(optional)</span></label>
                    <div id="categoryList" style="display:flex; flex-wrap:wrap; gap:10px; margin-top:8px;">
                        <?php foreach ($categories as $cat): ?>
                            <label style="display:flex; align-items:center; gap:6px;
                                          background:#f4f6fb; padding:6px 12px;
                                          border-radius:20px; font-size:0.875rem;
                                          cursor:pointer; font-weight:500;">
                                <input
                                    type="checkbox"
                                    name="categories[]"
                                    value="<?= $cat['id'] ?>"
                                    style="accent-color: #2e6cf6;"
                                >
                                <?= htmlspecialchars($cat['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="submitBtn" style="margin-top:8px;">
                    Upload File
                </button>

            </form>
        </section>

        <!-- ── Files table ── -->
        <section class="card">
            <h2 class="card-title">
                <?= $userRole === 'admin' ? 'All Uploaded Files' : 'Your Uploaded Files' ?>
            </h2>

            <?php if (empty($myFiles)): ?>
                <p class="text-muted text-center" style="padding:24px 0;">No files yet. Upload one above!</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <?php if ($userRole === 'admin'): ?><th>Owner</th><?php endif; ?>
                                <th>Size</th>
                                <th>Uploaded</th>
                                <th>Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myFiles as $file): ?>
                            <tr>
                                <td>
                                    <?php
                                    $ext  = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                                    $icon = match($ext) {
                                        'pdf'  => '📄',
                                        'jpg','jpeg','png','gif','webp' => '🖼️',
                                        'xls','xlsx','csv' => '📊',
                                        'doc','docx' => '📝',
                                        'zip','rar'  => '🗜️',
                                        default      => '📎',
                                    };
                                    ?>
                                    <?= $icon ?> <?= htmlspecialchars($file['original_name']) ?>
                                </td>
                                <?php if ($userRole === 'admin'): ?>
                                    <td><?= htmlspecialchars($file['uploader']) ?></td>
                                <?php endif; ?>
                                <td><?= formatSize((int)$file['file_size']) ?></td>
                                <td><?= date('d M Y', strtotime($file['uploaded_at'])) ?></td>
                                <td>
                                    <a href="<?= htmlspecialchars($file['file_path']) ?>"
                                       class="btn-sm btn-edit"
                                       download>
                                        ⬇ Download
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    </main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $(document).ready(function () {

        /* Hamburger toggle */
        $('#hamburger').on('click', function () {
            $('.sidebar').toggleClass('open');
            $('#overlay').toggleClass('open');
        });
        $('#overlay').on('click', function () {
            $('.sidebar, #overlay').removeClass('open');
        });

        // Click on the zone → open file browser
        $('#uploadZone').on('click', function () {
            $('#fileInput').trigger('click');
        });

        // When a file is chosen, show its name and update the zone text
        $('#fileInput').on('change', function () {
            var fileName = this.files[0] ? this.files[0].name : '';
            if (fileName) {
                $('#filePreview').text('Selected: ' + fileName).show();
                $('#uploadZone').css('border-color', '#2e6cf6');
            }
        });

        // Prevent submitting with no file selected
        $('#uploadForm').on('submit', function () {
            if (!$('#fileInput').val()) {
                alert('Please select a file first.');
                return false;
            }
            $('#submitBtn').text('Uploading…').prop('disabled', true);
        });

    });
</script>

</body>
</html>
