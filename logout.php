<?php
/**
 * Logout Pengurus BKRC
 */
require_once __DIR__ . '/config/helpers.php';

session_unset();
session_destroy();

header('Location: ' . getBaseUrl() . '/login.php');
exit;
