<?php
/**
 * dashboard.php
 * The main page shown after login.
 * Displays stat cards, a welcome message, and the user's recent uploaded files.
 * Admin users see ALL files; standard users see only their own.
 */

require_once 'Database.php';

session_start();

// ── Auth guard: redirect to login if not logged in ─────────
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Grab session values into short variables for easy use in HTML
$userId   = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userRole = $_SESSION['user_role'];

// Get the first letter of the name for the avatar circle
$avatarLetter = strtoupper($userName[0]);

$db = Database::getInstance();

// ── Stat 1: Total users (admin only) or just own account ───
if ($userRole === 'admin') {
    $totalUsers = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
} else {
    $totalUsers = 1; // standard user only sees themselves
}

// ── Stat 2: Total files (admin sees all, user sees own) ────
if ($userRole === 'admin') {
    $totalFiles = $db->query('SELECT COUNT(*) FROM files')->fetchColumn();
} else {
    $stmt = $db->prepare('SELECT COUNT(*) FROM files WHERE user_id = ?');
    $stmt->execute([$userId]);
    $totalFiles = $stmt->fetchColumn();
}

// ── Stat 3: Total categories ───────────────────────────────
$totalCategories = $db->query('SELECT COUNT(*) FROM categories')->fetchColumn();

// ── Recent files table (last 8 uploads) ────────────────────
// JOIN with users table to get the uploader's name
if ($userRole === 'admin') {
    // Admin sees every file plus who uploaded it
    $stmt = $db->query(
        'SELECT f.id, f.original_name, f.file_size, f.uploaded_at, u.name AS uploader
         FROM files f
         JOIN users u ON f.user_id = u.id
         ORDER BY f.uploaded_at DESC
         LIMIT 8'
    );
} else {
    // Standard user only sees their own files
    $stmt = $db->prepare(
        'SELECT f.id, f.original_name, f.file_size, f.uploaded_at, u.name AS uploader
         FROM files f
         JOIN users u ON f.user_id = u.id
         WHERE f.user_id = ?
         ORDER BY f.uploaded_at DESC
         LIMIT 8'
    );
    $stmt->execute([$userId]);
}
$recentFiles = $stmt->fetchAll();

// Helper: convert bytes into a readable string like "1.2 MB"
function formatFileSize(int $bytes): string {
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – CloudCMS</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<!-- Dark overlay — clicking it closes the sidebar on mobile -->
<div class="sidebar-overlay" id="overlay"></div>

<!-- Sticky top bar shown only on mobile (hamburger + brand) -->
<div class="mobile-topbar">
    <div class="mobile-brand">
        <div class="brand-icon">☁</div>
        CloudCMS
    </div>
    <button class="hamburger" id="hamburger" aria-label="Open menu">
        <span></span><span></span><span></span>
    </button>
</div>

<!-- ── Outer flex wrapper: sidebar + content side by side ── -->
<div class="layout">

    <!-- ════════════════ SIDEBAR ════════════════ -->
    <aside class="sidebar">

        <!-- Brand logo -->
        <div class="sidebar-brand">
            <div class="brand-icon">☁</div>
            <span class="brand-name">CloudCMS</span>
        </div>

        <!-- Navigation links — semantic <nav> element -->
        <nav>
            <a href="dashboard.php" class="active">
                <span class="nav-icon">📊</span> Overview
            </a>
            <a href="upload.php">
                <span class="nav-icon">📁</span> Upload File
            </a>
            <?php if ($userRole === 'admin'): ?>
                <!-- Admin-only link — only rendered when role is admin -->
                <a href="admin.php">
                    <span class="nav-icon">⚙️</span> Admin Panel
                </a>
            <?php endif; ?>
            <a href="profile.php">
                <span class="nav-icon">👤</span> Profile
            </a>
        </nav>

        <!-- Logout at the bottom of the sidebar -->
        <div class="sidebar-footer">
            <a href="logout.php">
                <span class="nav-icon">🚪</span> Log Out
            </a>
        </div>

    </aside>
    <!-- ══════════════ END SIDEBAR ══════════════ -->


    <!-- ════════════════ MAIN CONTENT ════════════════ -->
    <main class="main-content">

        <!-- Top bar: page title + user info -->
        <header class="topbar">
            <div class="topbar-title">
                <h1>Weekly Overview</h1>
                <p>Get a summary of your files and activity here.</p>
            </div>
            <div class="topbar-user">
                <!-- Avatar circle with first letter of the user's name -->
                <div class="avatar"><?= htmlspecialchars($avatarLetter) ?></div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($userName) ?></strong>
                    <small><?= $userRole === 'admin' ? 'Admin account' : 'Standard user' ?></small>
                </div>
            </div>
        </header>

        <!-- ── Stat Cards row ── -->
        <section class="stat-grid" id="statCards">

            <div class="stat-card">
                <div class="stat-icon">📂</div>
                <div class="stat-label">Total Files</div>
                <div class="stat-value" id="countFiles"><?= $totalFiles ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🏷️</div>
                <div class="stat-label">Categories</div>
                <div class="stat-value"><?= $totalCategories ?></div>
            </div>

            <?php if ($userRole === 'admin'): ?>
            <!-- This card is only visible to admins -->
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?= $totalUsers ?></div>
            </div>
            <?php endif; ?>

            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-label">Your Role</div>
                <div class="stat-value" style="font-size:1rem; text-transform:capitalize;">
                    <?= htmlspecialchars($userRole) ?>
                </div>
            </div>

        </section>

        <!-- ── Welcome banner ── -->
        <section class="card" id="welcomeBanner">
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                <div>
                    <h2 style="font-size:1.1rem; margin-bottom:4px;">
                        Welcome back, <?= htmlspecialchars($userName) ?>! 👋
                    </h2>
                    <p class="text-muted">
                        <?php if ($userRole === 'admin'): ?>
                            You have full admin access. Use the Admin Panel to manage users and export logs.
                        <?php else: ?>
                            Upload files and manage your documents from the sidebar.
                        <?php endif; ?>
                    </p>
                </div>
                <a href="upload.php" class="btn-primary" style="width:auto; padding:10px 22px; display:inline-block; text-decoration:none;">
                    + Upload File
                </a>
            </div>
        </section>

        <!-- ── Recent files table ── -->
        <section class="card">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
                <h2 class="card-title" style="margin:0;">
                    <?= $userRole === 'admin' ? 'All Recent Files' : 'Your Recent Files' ?>
                </h2>
                <!-- jQuery will hook onto this input to filter the table rows -->
                <input
                    type="text"
                    id="searchInput"
                    placeholder="🔍 Search files…"
                    style="padding:8px 12px; border:1.5px solid #e5e7eb; border-radius:8px;
                           font-size:0.875rem; outline:none; width:200px;"
                >
            </div>

            <?php if (empty($recentFiles)): ?>
                <!-- Shown when no files have been uploaded yet -->
                <p class="text-muted text-center" style="padding:32px 0;">
                    No files uploaded yet. <a href="upload.php">Upload your first file →</a>
                </p>
            <?php else: ?>

                <div class="table-wrap">
                    <!-- Semantic table for data (not for layout) -->
                    <table class="data-table" id="filesTable">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <?php if ($userRole === 'admin'): ?>
                                    <th>Uploaded By</th>
                                <?php endif; ?>
                                <th>Size</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentFiles as $file): ?>
                            <tr>
                                <td>
                                    <!-- File icon based on extension -->
                                    <?php
                                    $ext  = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                                    $icon = match($ext) {
                                        'pdf'             => '📄',
                                        'jpg','jpeg','png','gif','webp' => '🖼️',
                                        'xls','xlsx','csv'=> '📊',
                                        'doc','docx'      => '📝',
                                        'zip','rar'       => '🗜️',
                                        default           => '📎',
                                    };
                                    ?>
                                    <?= $icon ?> <?= htmlspecialchars($file['original_name']) ?>
                                </td>
                                <?php if ($userRole === 'admin'): ?>
                                    <td><?= htmlspecialchars($file['uploader']) ?></td>
                                <?php endif; ?>
                                <td><?= formatFileSize((int)$file['file_size']) ?></td>
                                <td><?= date('d M Y', strtotime($file['uploaded_at'])) ?></td>
                                <td>
                                    <a href="upload.php?view=<?= $file['id'] ?>" class="btn-sm btn-edit">
                                        View
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
    <!-- ══════════════ END MAIN CONTENT ══════════════ -->

</div><!-- end .layout -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
    $(document).ready(function () {

        /* Hamburger: open/close sidebar on mobile */
        $('#hamburger').on('click', function () {
            $('.sidebar').toggleClass('open');
            $('#overlay').toggleClass('open');
        });
        $('#overlay').on('click', function () {
            $('.sidebar, #overlay').removeClass('open');
        });

        /* Live search: filter table rows as the user types */
        $('#searchInput').on('keyup', function () {
            var query = $(this).val().toLowerCase(); // what the user typed

            // Loop through every row in the table body
            $('#filesTable tbody tr').each(function () {
                var rowText = $(this).text().toLowerCase(); // all text in this row
                // Show the row if it contains the search text, otherwise hide it
                $(this).toggle(rowText.indexOf(query) !== -1);
            });
        });

        /*
         * jQuery script 2: Animate the stat cards in one by one
         * when the page loads, giving a smooth staggered entrance.
         */
        $('.stat-card').each(function (index) {
            var card = $(this);
            card.css({ opacity: 0, transform: 'translateY(20px)' });
            setTimeout(function () {
                card.animate({ opacity: 1 }, 400);
                card.css('transform', 'translateY(0)');
            }, index * 120); // each card appears 120ms after the previous
        });

    });
</script>

</body>
</html>
