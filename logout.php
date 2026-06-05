<?php
/**
 * logout.php
 * Destroys the session and clears the cookie, then sends the user back to login.
 */

session_start();

// session_unset() acts like an eraser for the server's memory. It completely clears out
// all the variables currently stored inside the global $_SESSION array (like user_id, user_name, etc.).
session_unset();

// session_destroy() completely obliterates the physical session file stored on the server.
// Once this runs, the unique Session ID token the browser holds becomes entirely useless.
session_destroy();

// Expire the "remember name" cookie by setting a past date
setcookie('cms_last_user', '', time() - 3600, '/');

// Back to login page
header('Location: login.php');

// exit prevents the PHP engine from compiling or leaking any invisible background data 
exit;
