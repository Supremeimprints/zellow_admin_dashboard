<?php
session_start();
unset($_SESSION['first_time_login']);
echo json_encode(['success' => true]);
