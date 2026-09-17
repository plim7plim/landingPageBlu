<?php
// Config compartilhada pelos endpoints PHP (api/*.php).
// Lê o ".env" (nunca commitado, veja .gitignore) e expõe helpers simples de
// configuração, conexão com o banco e leitura/escrita de JSON.

function env(string $key, $default = null) {
  static $vars = null;
  if ($vars === null) {
    $vars = [];
    $path = __DIR__ . '/.env';
    if (is_file($path)) {
      foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$key2, $value] = array_map('trim', explode('=', $line, 2));
        $vars[$key2] = $value;
      }
    }
  }
  return $vars[$key] ?? $default;
}

function db(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $host = env('DB_HOST', 'localhost');
    $name = env('DB_NAME');
    $user = env('DB_USER');
    $pass = env('DB_PASS');
    $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }
  return $pdo;
}

function jsonInput(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function jsonResponse($data, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

function adminHeaderToken(): string {
  $headers = function_exists('getallheaders') ? getallheaders() : [];
  foreach ($headers as $key => $value) {
    if (strtolower($key) === 'x-admin-token') return $value;
  }
  return $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
}

function requireAdmin(): void {
  $expected = env('ADMIN_TOKEN');
  $token = adminHeaderToken();
  if (!$expected || !hash_equals($expected, $token)) {
    jsonResponse(['ok' => false, 'error' => 'Não autorizado.'], 401);
  }
}
