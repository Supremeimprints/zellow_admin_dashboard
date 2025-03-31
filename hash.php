<?php
// Example passwords for each user
$passwords = [
    'geoffreymagana3@gmail.com' => '12345678',
    
];

// Hash each password and display the hashed password for insertion into the database
foreach ($passwords as $username => $password) {
    echo $username . ': ' . password_hash($password, PASSWORD_DEFAULT) . "<br>";
}
