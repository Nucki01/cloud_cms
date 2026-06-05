<?php
/**
 * login.php
 * Handles user login: shows the form and processes the POST submission.
 * Uses PHP Sessions to keep the user logged in across pages.
 */

// It imports your database connection class so we can run SQL queries.
require_once 'Database.php';


// session_start() tells the server to look for an existing session ID cookie.
// If none exists, it creates a new session. This lets us persist data across different pages.
session_start();


if (isset($_SESSION['user_id'])) {
    
    header('Location: dashboard.php');
    exit;
}

$error = ''; 

// ── Process the form when it is submitted ──────────────────
// $_SERVER is a PHP superglobal. 'REQUEST_METHOD' checks if the page was accessed 
// via a standard page load (GET) or a form submission (POST).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    
    // $_POST fetches data sent via the HTTP POST method using the form input 'name' attributes.
    // trim() strips accidental whitespace from the beginning and end of the string.
    // ?? '' is the Null Coalescing Operator. If $_POST['email'] doesn't exist, it defaults to an empty string.
    $email    = trim($_POST['email']    ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // Basic check: both fields must have a value
    if ($email === '' || $password === '') {
        $error = 'Please fill in both fields.';
    } else {
        // Look up the user by email in the database,Singleton pattern call ensuring we use a single, shared DB connection.
        $db   = Database::getInstance();

        // SQL SECURED: We use a prepared statement ('?'). This prevents SQL Injection attacks 
        // because the database treats the parameter strictly as data, never as executable code.
        $stmt = $db->prepare('SELECT id, name, password, role FROM users WHERE email = ? LIMIT 1');

        // execute() runs the SQL query, safely binding the $email variable to the '?' placeholder.
        $stmt->execute([$email]);

        // fetch() retrieves the matched database row as an associative array. 
        // If no user matches the email, fetch() returns false.
        $user = $stmt->fetch(); // returns an array or false

        // password_verify() checks the plain password against the stored hash
        if ($user && password_verify($password, $user['password'])) {
            // Correct credentials — save user info into the session
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            // Also set a cookie to remember the user's name for 7 days
            // setcookie() stores data directly on the user's browser.
            setcookie('cms_last_user', $user['name'], time() + (7 * 24 * 3600), '/');

            // Send them to the main dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            // Generic error message for security. Never specify whether the email or password was the wrong choice,
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – CloudCMS</title>
    
    <link rel="stylesheet" href="css/style.css">
</head>

<!--class that centres the card vertically + applies gradient background -->
<body class="auth-body">

    <main>
        <div class="auth-card">

            <!-- brand logo area -->
            <div class="auth-brand">
                <div class="brand-icon">☁</div>
                <span>CloudCMS</span>
            </div>

            <h1>Welcome back</h1>
            <p class="subtitle">Sign in to your account to continue.</p>

            <!-- Show error message if login failed -->
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!--
                Semantic form:
                  action=""      → submits to this same file
                  method="post"  → POST keeps passwords out of the URL
            -->
            <form action="" method="post" id="loginForm" novalidate>

                <!-- Email field -->
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

                <!-- Password field -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        required
                    >
                </div>

                <!-- Submit button -->
                <button type="submit" class="btn-primary" id="submitBtn">
                    Sign In
                </button>

            </form>

            <p class="auth-footer">
                Don't have an account? <a href="register.php">Create one here</a>
            </p>

        </div>
    </main>

    <!-- jQuery loaded from CDN (rubric: Client Scripting) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        /*
         * jQuery script: client-side form validation.
         * Prevents the form from submitting if fields are empty,
         * and shows a loading state on the button while submitting.
         */
        $(document).ready(function () {

            // Attaches a listener to the form element. When the user clicks "Sign In" or hits Enter...
            $('#loginForm').on('submit', function (e) {
                // $('#id').val() grabs the current input string value.
                // .trim() removes leading/trailing whitespaces in JavaScript.
                var email    = $('#email').val().trim();
                var password = $('#password').val().trim();

                // Simple client-side check before sending to server
                if (email === '' || password === '') {
                    // e.preventDefault() blocks the natural form submission event. 
                    // This stops the page from reloading and sending data to the server.
                    e.preventDefault(); // stop the form from submitting
                    alert('Please fill in your email and password.');
                    return;
                }

                // Change button text to show something is happening
                //Updates the text inside the button to show processing progress.
                $('#submitBtn').text('Signing in…').prop('disabled', true);
            });

            // If the page has an error, shake the card slightly to draw attention
            // If there's an error, PHP prints this block of jQuery directly into the script before serving it to the browser.
            <?php if ($error !== ''): ?>
                // Clears out any default CSS-based entry animations on the login card
                $('.auth-card').css('animation', 'none');
                
                // setTimeout creates a  delay (100 milliseconds) before running the inner animation.
                setTimeout(function () {
                    $('.auth-card').animate({ marginLeft: '-8px' }, 60)
                                  .animate({ marginLeft:  '8px' }, 60)
                                  .animate({ marginLeft:  '0px' }, 60);
                }, 100);
            <?php endif; ?>

        });
    </script>

</body>
</html>
