<?php
/**
 * logout.php
 * Destroys the session and clears the cookie, then sends the user back to login.
 */

// session_start() must be called first to gain access to the user's existing session.
// The server cannot target or destroy a session unless it initializes a link to it first.
session_start();

// Remove all session data
// session_unset() acts like an eraser for the server's memory. It completely clears out
// all the variables currently stored inside the global $_SESSION array (like user_id, user_name, etc.).
session_unset();

// session_destroy() completely obliterates the physical session file stored on the server.
// Once this runs, the unique Session ID token the browser holds becomes entirely useless.
session_destroy();

// Expire the "remember name" cookie by setting a past date
// To delete a cookie from a user's browser, you cannot simply use a "delete" command. Instead,
// you overwrite the existing cookie with an expiration date that has already passed.
// time() - 3600 takes the current time and subtracts 1 hour (3600 seconds) into the past.
// When the browser receives this, it sees it is expired and instantly deletes the cookie file.
setcookie('cms_last_user', '', time() - 3600, '/');

// Back to login page
// Sends a raw HTTP header redirect instruction back to the user's browser.
header('Location: login.php');

// exit prevents the PHP engine from compiling or leaking any invisible background data 
// after the redirect occurs.
exit;
