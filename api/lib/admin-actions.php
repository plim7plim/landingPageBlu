<?php
// Confirmação de intenção, vinculada ao cadastro e ao destino. Não substitui o token admin.
function approvalSession(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('blu_approval');
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict',
      'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'use_strict_mode' => true]);
  }
}

function approvalFingerprint(array $record): string {
  return hash('sha256', json_encode([
    (string) $record['id'], $record['commercial_email'], $record['status'],
    $record['status_updated_at'] ?? null, $record['archived_at'] ?? null,
    env('SOUBLU_LOGIN_URL', 'https://soumaisblu.com.br/index.html'),
    env('SOUBLU_API_BASE_URL', ''),
  ]));
}

function issueApprovalChallenge(array $record): array {
  approvalSession();
  $now = time();
  $challenges = array_filter($_SESSION['approval_challenges'] ?? [], fn($item) => $item['expires'] > $now);
  if (count($challenges) >= 10) array_shift($challenges);
  $nonce = bin2hex(random_bytes(24));
  $code = (string) random_int(100000, 999999);
  $challenges[$nonce] = ['code' => $code, 'expires' => $now + 300,
    'fingerprint' => approvalFingerprint($record), 'admin' => hash('sha256', adminHeaderToken())];
  $_SESSION['approval_challenges'] = $challenges;
  session_write_close();
  return ['confirmationToken' => $nonce, 'code' => $code, 'expiresIn' => 300,
    'name' => $record['name'], 'email' => $record['commercial_email'],
    'loginUrl' => env('SOUBLU_LOGIN_URL', 'https://soumaisblu.com.br/index.html')];
}

function consumeApprovalChallenge(array $record, array $input): bool {
  approvalSession();
  $nonce = is_string($input['confirmationToken'] ?? null) ? $input['confirmationToken'] : '';
  $challenge = $_SESSION['approval_challenges'][$nonce] ?? null;
  unset($_SESSION['approval_challenges'][$nonce]);
  session_write_close();
  return $challenge && $challenge['expires'] > time()
    && hash_equals($challenge['admin'], hash('sha256', adminHeaderToken()))
    && hash_equals($challenge['fingerprint'], approvalFingerprint($record))
    && is_string($input['confirmationCode'] ?? null)
    && hash_equals($challenge['code'], $input['confirmationCode']);
}
