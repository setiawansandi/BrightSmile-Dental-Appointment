<?php
 // session + helpers + db()
declare(strict_types=1);

// --- Session config ---
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
  'lifetime' => 1800, // 30 mins in s
  'path'     => '/',
  'secure'   => !empty($_SERVER['HTTPS']),
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();

// --- DB config ---
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'brightsmile';

function db(): mysqli {
  $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if ($conn->connect_error) die('Connection failed: '.$conn->connect_error);
  $conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
  return $conn;
}

// (optional) tiny helpers
function redirect(string $url): void { header("Location: $url"); exit; }
