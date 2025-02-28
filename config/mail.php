<?php
// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'supremeimprints@gmail.com'); // Replace with your Gmail address
define('SMTP_PASSWORD', 'wzjwpxyiqpzsyttx'); // Replace with your App Password
define('SMTP_FROM_EMAIL', 'supremeimprints@gmail.com'); // Replace with your Gmail address
define('SMTP_FROM_NAME', 'Zellow Admin');

// Debug settings
define('SMTP_DEBUG', 0); // 0 = off, 1 = client, 2 = client/server
define('SMTP_SECURE', 'tls'); // tls or ssl
define('SMTP_AUTH', true); // true or false
define('SMTP_VERIFY_PEER', false); // For development only, set to true in production

/*
Important: For Gmail, you need to:
1. Enable 2-Step Verification on your Google Account
2. Generate an App Password:
   - Go to Google Account settings
   - Security
   - 2-Step Verification
   - App passwords (at the bottom)
   - Generate a new app password for "Mail"
3. Use that 16-character app password as SMTP_PASSWORD
*/

/*
Debug levels:
0 = No output
1 = Commands
2 = Data and commands
3 = As 2 plus connection status
4 = Low-level data output
*/
?>
