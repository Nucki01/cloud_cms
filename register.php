<?php
/**
 * register.php
 * Handles new user registration.
 * On success it saves the user to the database and redirects to login.
 */

require_once 'Database.php';

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error   = '';
$success = '';

// ── Process the registration form ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitizes and intercepts the input field parameters using data arrays passed by the browser.
    // trim() drops accidental whitespace. ?? '' sets a fallback empty string if a field is completely missing.
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    // ---- Validation ----
    // Stage 1: Absolute Presence. Double check that no field arrived completely blank.
    if ($name === '' || $email === '' || $password === '' || $confirm === '') {
        $error = 'All fields are required.';

    // Stage 2: Pattern Matching. filter_var ensures the text strictly matches a legal global email syntax structure.
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    // Stage 3: Complexity Check. Evaluates length metric to prevent short, easily guessable passwords.
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';

    // Stage 4: Congruency Check. Confirms structural equality between both input password entries.
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';

    } else {
        // Validation passed! Requesting the singular connection point interface to our SQL database server.
        $db = Database::getInstance();

        // Check if this email is already registered
        // Prepared SQL execution template logic prevents SQL Injection vulnerabilities by parameterizing values.
        $check = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        // Safely maps the user-provided $email directly into the variable placeholder.
        $check->execute([$email]);

        // If fetch() finds a database row, it means this email already exists in our records.
        if ($check->fetch()) {
            $error = 'This email address is already registered.';
        } else {
            // Hash the password before storing — NEVER store plain text
            // PASSWORD_BCRYPT applies a highly secure one-way slow mathematical encryption algorithm.
            // This ensures that even if hackers breach the database table, they cannot decode your true user passwords.
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // Insert the new user (role defaults to 'user' in the DB schema)
            $insert = $db->prepare(
                'INSERT INTO users (name, email, password) VALUES (?, ?, ?)'
            );
            // Binds the variables into the system sequence block execution cycle.
            $insert->execute([$name, $email, $hashedPassword]);

            // Formats success trigger state message to modify UI viewport blocks downstream.
            $success = 'Account created! You can now sign in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – CloudCMS</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="auth-body">

    <main>
        <div class="auth-card">

            <!-- Brand -->
            <div class="auth-brand">
                <div class="brand-icon">☁</div>
                <span>CloudCMS</span>
            </div>

            <h1>Create an account</h1>
            <p class="subtitle">Join CloudCMS to start managing your files.</p>

            <!-- Error or success message -->
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                    <br><a href="login.php">Go to Login →</a>
                </div>
            <?php endif; ?>

            <!--
                Registration form
                method="post" keeps data out of the URL
                required attributes provide basic HTML5 validation
            -->
            <form action="" method="post" id="registerForm" novalidate>

                <!-- Full name -->
                <div class="form-group">
                    <label for="name">Full name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Andrew Smith"
                        required
                        value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                    >
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password <span class="text-muted">(min. 6 characters)</span></label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        required
                    >
                </div>

                <!-- Confirm password -->
                <div class="form-group">
                    <label for="confirm">Confirm password</label>
                    <input
                        type="password"
                        id="confirm"
                        name="confirm"
                        placeholder="••••••••"
                        required
                    >
                    <!-- jQuery will show this span if passwords don't match -->
                    <span id="matchMsg" class="text-muted mt-8" style="display:none; font-size:0.8rem;"></span>
                </div>

                <button type="submit" class="btn-primary" id="submitBtn">
                    Create Account
                </button>

            </form>

            <p class="auth-footer">
                Already have an account? <a href="login.php">Sign in</a>
            </p>

        </div>
    </main>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        /*
         * jQuery script:
         * 1. Live password-match feedback as the user types.
         * 2. Client-side validation before the form is submitted.
         */
        // Holds script runtime steps secure until the primary browser window DOM structure has fully assembled.
        $(document).ready(function () {

            // Live check: compare password and confirm fields on every keystroke
            // .on('keyup') triggers immediately every time a key is released inside the Confirm Input field.
            $('#confirm').on('keyup', function () {
                var pw  = $('#password').val();  // Pulls value currently inside primary password input
                var cfm = $(this).val();  // Pulls value currently inside this confirm password input

                // Reset state: if they delete everything in confirm input, hide warning element
                if (cfm.length === 0) {
                    $('#matchMsg').hide();
                    return;
                }

                // Logic evaluation block processing real-time feedback messages
                if (pw === cfm) {
                    // Strings match exactly: set text value status, turn label green, make visible
                    $('#matchMsg').text('✓ Passwords match').css('color', '#16a34a').show();
                } else {
                    // Strings differ: alter diagnostic tracking alerts, turn label red, make visible
                    $('#matchMsg').text('✗ Passwords do not match').css('color', '#dc2626').show();
                }
            });

            // Validate before submit
            // Event listener traps form submit actions prior to network routing steps
            $('#registerForm').on('submit', function (e) {
                var name  = $('#name').val().trim();
                var email = $('#email').val().trim();
                var pw    = $('#password').val();
                var cfm   = $('#confirm').val();

                // Frontend validation catch: check for presence across variables
                if (!name || !email || !pw || !cfm) {
                    e.preventDefault();
                    alert('Please fill in all fields.');
                    return;
                }

                // Frontend validation catch: ensures strings match prior to transmission
                if (pw !== cfm) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    return;
                }

                // Frontend validation catch: checks character length criteria constraints
                if (pw.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters.');
                    return;
                }

                // All good — show loading state
                // Inhibits double submission risk actions by setting element property flag to disabled state
                $('#submitBtn').text('Creating account…').prop('disabled', true);
            });

        });
    </script>

</body>
</html>
