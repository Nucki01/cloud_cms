<?php
/*
 * admin.php is admin page   where you have special features
 *   - View all users (Select)
 *   - Change a user's role (Update)
 *   - Delete a user (Delete)
 *   - Export a user log to a .txt file
 */


// Include the Singleton Database class to interact with the DB
require_once 'Database.php';

//starting session
session_start();

// Only admins may access this page 
// // SECURITY CHECK: Only allow access if the user is authenticated and has an 'admin' role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    // Redirect unauthorized users back to the dashboard and stop script execution
    header('Location: dashboard.php');
    exit;
}

// Map session data to local variables for ease of use in the HTML markup
$userId       = $_SESSION['user_id'];
$userName     = $_SESSION['user_name'];
$userRole     = $_SESSION['user_role'];
// Grab the first letter of the username for the profile placeholder
$avatarLetter = strtoupper($userName[0]); 

// Get database instance
$db      = Database::getInstance();
$message = ''; // Stores feedback messages shown to the user
$msgType = 'success'; // Toggles styling for error vs success feedback boxes

// ── ACTION: Export log to .txt file ───────────────────────
// Triggers when the 'Export to .txt' form button is clicked
if (isset($_POST['export_log'])) {

    // Fetch all users for the log
    // Fetch all columns required for the text log from the users table
    $logUsers = $db->query(
        'SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC'
    )->fetchAll();

    // Build the text content line by line
    // Initialize string and append metadata headers to the text log
    $logContent  = "CloudCMS – User Log Export\n";
    $logContent .= "Generated: " . date('Y-m-d H:i:s') . "\n";
    $logContent .= "Exported by: {$userName}\n";
    $logContent .= str_repeat('-', 60) . "\n\n";

    // Loop over each user in the database array and format their details cleanly
    foreach ($logUsers as $u) {
        $logContent .= "ID      : {$u['id']}\n";
        $logContent .= "Name    : {$u['name']}\n";
        $logContent .= "Email   : {$u['email']}\n";
        $logContent .= "Role    : {$u['role']}\n";
        $logContent .= "Joined  : {$u['created_at']}\n";
        $logContent .= str_repeat('-', 40) . "\n";
    }

    // Write the text to a file in the project root
    // Write the compiled raw text into a file sitting in the same directory as this script
    $logFile = __DIR__ . '/user_log.txt';
    file_put_contents($logFile, $logContent);

    $message = 'Log exported successfully to user_log.txt';
}

// ── ACTION: Update a user's role ──────────────────────────
// Triggers when an administrator alters a role dropdown and clicks 'Save'
if (isset($_POST['update_role'])) {
    // Typecast the ID to an integer for type safety; default to 0 if missing
    $targetId   = (int)($_POST['target_id']  ?? 0);
    $targetRole = $_POST['new_role'] ?? '';

    // Make sure the role value is only 'admin' or 'user'
    // Data validation: Verify ID is real and role inputs map exactly to system specifications
    if ($targetId > 0 && in_array($targetRole, ['admin', 'user'])) {

        // Prevent admin from demoting themselves
        // CRITICAL BUSINESS LOGIC: Prevent active administrators from accidentally demoting themselves
        if ($targetId === $userId) {
            $message = 'You cannot change your own role.';
            $msgType = 'error';
        } else {
            // Update role setting using a parameterized statement to block SQL Injection
            $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
            $stmt->execute([$targetRole, $targetId]);
            $message = 'User role updated successfully.';
        }
    }
}

// ── ACTION: Delete a user ─────────────────────────────────
// Triggers when a user's 'Delete' button form is submitted
if (isset($_POST['delete_user'])) {
    $targetId = (int)($_POST['target_id'] ?? 0);

    // Business Logic Validation: Ensure ID is valid and prevent suicide/self-deletion of accounts
    if ($targetId > 0 && $targetId !== $userId) {

        // CASCADE in the DB schema also deletes their files automatically
        // Run parameterized query. Note: Database level Foreign Key (ON DELETE CASCADE) must handle related files.
        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$targetId]);
        $message = 'User deleted successfully.';
    } else {
        $message = 'You cannot delete your own account here.';
        $msgType = 'error';
    }
}

// ── SELECT: Load all users for the table ─────────────────
// Performs a LEFT JOIN against the files table to calculate aggregated file stats per user profile
$users = $db->query(
    'SELECT u.id, u.name, u.email, u.role, u.created_at,
            COUNT(f.id) AS file_count
     FROM users u
     LEFT JOIN files f ON f.user_id = u.id
     GROUP BY u.id
     ORDER BY u.created_at DESC'
)->fetchAll();

// ── Load all files for the files overview section ─────────
// Fetches the 10 most recent global uploads paired with their owner names via an INNER JOIN
$allFiles = $db->query(
    'SELECT f.id, f.original_name, f.file_size, f.uploaded_at, u.name AS uploader
     FROM files f
     JOIN users u ON f.user_id = u.id
     ORDER BY f.uploaded_at DESC
     LIMIT 10'
)->fetchAll();

//Formats file sizes from raw bytes to human-readable units (B, KB, MB)
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
    <title>Admin Panel – CloudCMS</title>
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
            <a href="upload.php"><span class="nav-icon">📁</span> Upload File</a>
            <a href="admin.php" class="active"><span class="nav-icon">⚙️</span> Admin Panel</a>
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
                <h1>Admin Panel</h1>
                <p>Manage users, roles, and export logs.</p>
            </div>
            <div class="topbar-user">
                <div class="avatar"><?= htmlspecialchars($avatarLetter) ?></div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($userName) ?></strong>
                    <small>Admin account</small>
                </div>
            </div>
        </header>

        <!-- Feedback message -->
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- ── Stat row ── -->
        <section class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?= count($users) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📂</div>
                <div class="stat-label">Total Files</div>
                <div class="stat-value">
                    <?= $db->query('SELECT COUNT(*) FROM files')->fetchColumn() ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🛡️</div>
                <div class="stat-label">Admins</div>
                <div class="stat-value">
                    <?= $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() ?>
                </div>
            </div>
        </section>

        <!-- ── Export log button ── -->
        <section class="card">
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                <div>
                    <h2 class="card-title" style="margin:0;">Export User Log</h2>
                    <p class="text-muted">Write all user info into a plain <strong>user_log.txt</strong> file.</p>
                </div>
                <!-- Single-button form — POST action keeps it clean -->
                <form action="" method="post">
                    <button type="submit" name="export_log" class="btn-primary btn-export">
                        ⬇ Export to .txt
                    </button>
                </form>
            </div>
            <?php if (file_exists(__DIR__ . '/user_log.txt')): ?>
                <p class="text-muted mt-8" style="font-size:0.8rem;">
                    Last export: <?= date('d M Y H:i', filemtime(__DIR__ . '/user_log.txt')) ?>
                    — <a href="user_log.txt" download>Download file</a>
                </p>
            <?php endif; ?>
        </section>

        <!-- ── User management table ── -->
        <section class="card">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                <h2 class="card-title" style="margin:0;">All Users</h2>
                <input type="text" id="userSearch" placeholder="🔍 Search users…"
                    style="padding:8px 12px; border:1.5px solid #e5e7eb; border-radius:8px;
                           font-size:0.875rem; outline:none; width:200px;">
            </div>

            <div class="table-wrap">
                <table class="data-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Files</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="badge badge-<?= $u['role'] ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td><?= $u['file_count'] ?></td>
                            <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                            <td class="action-cell" style="display:flex; gap:6px; flex-wrap:wrap;">

                                <?php if ($u['id'] !== $userId): ?>

                                    <!-- Change role form -->
                                    <form action="" method="post" style="display:inline;">
                                        <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                        <select name="new_role"
                                            style="padding:4px 8px; border-radius:6px; border:1.5px solid #e5e7eb;
                                                   font-size:0.8rem; cursor:pointer;">
                                            <option value="user"  <?= $u['role'] === 'user'  ? 'selected' : '' ?>>User</option>
                                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                        <button type="submit" name="update_role" class="btn-sm btn-edit">
                                            Save
                                        </button>
                                    </form>

                                    <!-- Delete user form -->
                                    <form action="" method="post" style="display:inline;"
                                          class="deleteForm">
                                        <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="delete_user" class="btn-sm btn-danger">
                                            Delete
                                        </button>
                                    </form>

                                <?php else: ?>
                                    <!-- Can't edit your own row -->
                                    <span class="text-muted" style="font-size:0.8rem;">You</span>
                                <?php endif; ?>

                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ── Recent files overview ── -->
        <section class="card">
            <h2 class="card-title">Recent Uploads (all users)</h2>
            <?php if (empty($allFiles)): ?>
                <p class="text-muted text-center" style="padding:20px 0;">No files uploaded yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Owner</th>
                                <th>Size</th>
                                <th>Uploaded</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allFiles as $file): ?>
                            <tr>
                                <td><?= htmlspecialchars($file['original_name']) ?></td>
                                <td><?= htmlspecialchars($file['uploader']) ?></td>
                                <td><?= formatSize((int)$file['file_size']) ?></td>
                                <td><?= date('d M Y', strtotime($file['uploaded_at'])) ?></td>
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

        //Toggle mobile UI off-canvas layout side drawers
        $('#hamburger').on('click', function () {
            $('.sidebar').toggleClass('open');
            $('#overlay').toggleClass('open');
        });
        $('#overlay').on('click', function () {
            $('.sidebar, #overlay').removeClass('open');
        });

        /* jQuery: live search through the users table */
        //Frontend Engine: Live local regex DOM matching filtration against target records 
        $('#userSearch').on('keyup', function () {
            var q = $(this).val().toLowerCase();
            $('#usersTable tbody tr').each(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
            });
        });

        /* jQuery: confirm before deleting a user */
        //Client-side confirmation interceptor to verify intentions before firing destructive deletions
        $('.deleteForm').on('submit', function (e) {
            if (!confirm('Are you sure you want to delete this user? Their files will also be removed.')) {
                e.preventDefault();
            }
        });

        /* Animate stat cards on load */
        //Staggered sequential execution of statistical data metric representations on screen
        $('.stat-card').each(function (i) {
            var card = $(this);
            card.css({ opacity: 0 });
            setTimeout(function () {
                card.animate({ opacity: 1 }, 350);
            }, i * 100);
        });

    });
</script>

</body>
</html>
