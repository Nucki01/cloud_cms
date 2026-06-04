<?php
/**
 * profile.php
 * Lets any logged-in user view their account info and update their name/password.
 * Also shows a list of their own uploaded files with a delete option.
 */

require_once 'Database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId       = $_SESSION['user_id'];
$userName     = $_SESSION['user_name'];
$userRole     = $_SESSION['user_role'];
$avatarLetter = strtoupper($userName[0]);

$db      = Database::getInstance();
$message = '';
$msgType = 'success';

// ── ACTION: Update name and/or password ───────────────────
if (isset($_POST['update_profile'])) {
    $newName    = trim($_POST['name']     ?? '');
    $newPass    = trim($_POST['password'] ?? '');
    $confirmPass= trim($_POST['confirm']  ?? '');

    if ($newName === '') {
        $message = 'Name cannot be empty.';
        $msgType = 'error';

    } elseif ($newPass !== '' && strlen($newPass) < 6) {
        $message = 'New password must be at least 6 characters.';
        $msgType = 'error';

    } elseif ($newPass !== '' && $newPass !== $confirmPass) {
        $message = 'Passwords do not match.';
        $msgType = 'error';

    } else {
        if ($newPass !== '') {
            // Update both name and password
            $hashed = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt   = $db->prepare('UPDATE users SET name = ?, password = ? WHERE id = ?');
            $stmt->execute([$newName, $hashed, $userId]);
        } else {
            // Update name only
            $stmt = $db->prepare('UPDATE users SET name = ? WHERE id = ?');
            $stmt->execute([$newName, $userId]);
        }

        // Keep session name in sync
        $_SESSION['user_name'] = $newName;
        $userName              = $newName;
        $avatarLetter          = strtoupper($newName[0]);
        $message               = 'Profile updated successfully.';
    }
}

// ── ACTION: Delete one of the user's own files ────────────
if (isset($_POST['delete_file'])) {
    $fileId = (int)($_POST['file_id'] ?? 0);

    if ($fileId > 0) {
        // Fetch the stored file path first so we can delete from disk
        $stmt = $db->prepare('SELECT stored_name FROM files WHERE id = ? AND user_id = ?');
        $stmt->execute([$fileId, $userId]);
        $fileRow = $stmt->fetch();

        if ($fileRow) {
            // Remove the physical file from the uploads folder
            $diskPath = __DIR__ . '/uploads/' . $fileRow['stored_name'];
            if (file_exists($diskPath)) {
                unlink($diskPath);
            }
            // Remove the database record (cascade removes file_categories rows too)
            $del = $db->prepare('DELETE FROM files WHERE id = ? AND user_id = ?');
            $del->execute([$fileId, $userId]);
            $message = 'File deleted.';
        }
    }
}

// Load fresh user data from DB (in case name was just updated)
$user = $db->prepare('SELECT id, name, email, role, created_at FROM users WHERE id = ?');
$user->execute([$userId]);
$user = $user->fetch();

// Load this user's files
$myFiles = $db->prepare(
    'SELECT f.id, f.original_name, f.file_size, f.uploaded_at,
            GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ", ") AS categories
     FROM files f
     LEFT JOIN file_categories fc ON fc.file_id = f.id
     LEFT JOIN categories c ON c.id = fc.category_id
     WHERE f.user_id = ?
     GROUP BY f.id
     ORDER BY f.uploaded_at DESC'
);
$myFiles->execute([$userId]);
$myFiles = $myFiles->fetchAll();

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
    <title>My Profile – CloudCMS</title>
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
            <?php if ($userRole === 'admin'): ?>
                <a href="admin.php"><span class="nav-icon">⚙️</span> Admin Panel</a>
            <?php endif; ?>
            <a href="profile.php" class="active"><span class="nav-icon">👤</span> Profile</a>
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
                <h1>My Profile</h1>
                <p>View and update your account details.</p>
            </div>
            <div class="topbar-user">
                <div class="avatar"><?= htmlspecialchars($avatarLetter) ?></div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($userName) ?></strong>
                    <small><?= $userRole === 'admin' ? 'Admin account' : 'Standard user' ?></small>
                </div>
            </div>
        </header>

        <!-- Feedback message -->
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Two-column layout on wider screens, single column on mobile -->
        <div class="profile-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; align-items:start;">

            <!-- ── Account info card ── -->
            <section class="card">
                <!-- Large avatar circle -->
                <div style="text-align:center; margin-bottom:20px;">
                    <div style="width:72px; height:72px; border-radius:50%;
                                background:linear-gradient(135deg,#2e6cf6,#1a4fd6);
                                color:#fff; font-size:1.8rem; font-weight:700;
                                display:flex; align-items:center; justify-content:center;
                                margin:0 auto 10px; box-shadow:0 4px 16px rgba(46,108,246,0.3);">
                        <?= htmlspecialchars($avatarLetter) ?>
                    </div>
                    <strong><?= htmlspecialchars($user['name']) ?></strong><br>
                    <span class="text-muted" style="font-size:0.85rem;"><?= htmlspecialchars($user['email']) ?></span><br>
                    <span class="badge badge-<?= $user['role'] ?>" style="margin-top:6px;">
                        <?= ucfirst($user['role']) ?>
                    </span>
                </div>

                <hr class="divider">

                <p class="text-muted" style="font-size:0.82rem; margin-bottom:4px;">
                    Member since: <strong><?= date('d M Y', strtotime($user['created_at'])) ?></strong>
                </p>
                <p class="text-muted" style="font-size:0.82rem;">
                    Total files: <strong><?= count($myFiles) ?></strong>
                </p>
            </section>

            <!-- ── Edit profile form card ── -->
            <section class="card">
                <h2 class="card-title">Edit Profile</h2>

                <!--
                    Form to update name and password.
                    method="post" + required on the name field.
                -->
                <form action="" method="post" id="profileForm">

                    <div class="form-group">
                        <label for="name">Full name</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?= htmlspecialchars($user['name']) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">Email address <span class="text-muted">(cannot be changed)</span></label>
                        <input
                            type="email"
                            value="<?= htmlspecialchars($user['email']) ?>"
                            disabled
                            style="opacity:0.6; cursor:not-allowed;"
                        >
                    </div>

                    <hr class="divider">
                    <p class="text-muted" style="font-size:0.82rem; margin-bottom:12px;">
                        Leave password fields blank to keep your current password.
                    </p>

                    <div class="form-group">
                        <label for="password">New password</label>
                        <input type="password" id="password" name="password" placeholder="••••••••">
                    </div>

                    <div class="form-group">
                        <label for="confirm">Confirm new password</label>
                        <input type="password" id="confirm" name="confirm" placeholder="••••••••">
                        <span id="matchMsg" style="font-size:0.8rem; display:none; margin-top:4px; display:block;"></span>
                    </div>

                    <button type="submit" name="update_profile" class="btn-primary">
                        Save Changes
                    </button>

                </form>
            </section>

        </div>

        <!-- ── My files table ── -->
        <section class="card" style="margin-top:24px;">
            <h2 class="card-title">My Uploaded Files</h2>

            <?php if (empty($myFiles)): ?>
                <p class="text-muted text-center" style="padding:24px 0;">
                    No files yet. <a href="upload.php">Upload one →</a>
                </p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Categories</th>
                                <th>Size</th>
                                <th>Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myFiles as $file): ?>
                            <tr>
                                <td><?= htmlspecialchars($file['original_name']) ?></td>
                                <td>
                                    <?php if ($file['categories']): ?>
                                        <span class="badge badge-blue"><?= htmlspecialchars($file['categories']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatSize((int)$file['file_size']) ?></td>
                                <td><?= date('d M Y', strtotime($file['uploaded_at'])) ?></td>
                                <td style="display:flex; gap:6px;">
                                    <a href="<?= htmlspecialchars('uploads/' . $file['id']) ?>"
                                       class="btn-sm btn-edit" download>⬇</a>

                                    <!-- Delete file form -->
                                    <form action="" method="post" class="deleteFileForm" style="display:inline;">
                                        <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                        <button type="submit" name="delete_file" class="btn-sm btn-danger">
                                            Delete
                                        </button>
                                    </form>
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

        /* jQuery: live password match indicator */
        $('#confirm').on('keyup', function () {
            var pw  = $('#password').val();
            var cfm = $(this).val();
            if (cfm.length === 0) { $('#matchMsg').hide(); return; }
            if (pw === cfm) {
                $('#matchMsg').text('✓ Passwords match').css('color','#16a34a').show();
            } else {
                $('#matchMsg').text('✗ Passwords do not match').css('color','#dc2626').show();
            }
        });

        /* jQuery: confirm before deleting a file */
        $('.deleteFileForm').on('submit', function (e) {
            if (!confirm('Delete this file? This cannot be undone.')) {
                e.preventDefault();
            }
        });

        /* jQuery: animate the two top cards in on load */
        $('.card').each(function (i) {
            var c = $(this);
            c.css({ opacity: 0, marginTop: '12px' });
            setTimeout(function () {
                c.animate({ opacity: 1, marginTop: '0px' }, 300);
            }, i * 80);
        });

    });
</script>

</body>
</html>
