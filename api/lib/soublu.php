<?php
// Integração com a API REST da Sou+Blu (mesma usada pelo painel principal,
// ver js/db-connect.js e js/db.js::addUser/savePartner no repositório do
// sistema). Replica o fluxo de admin.js::savePartner() para criar
// automaticamente um usuário nível "parceiro" quando um cadastro desta
// landing page é aprovado.

function soubluRest(string $method, string $table, ?array $body = null, string $query = ''): array {
  $base = rtrim(env('SOUBLU_API_BASE_URL', ''), '/');
  $apiKey = env('SOUBLU_API_KEY', '');
  if (!$base || !$apiKey) {
    throw new RuntimeException('SOUBLU_API_BASE_URL/SOUBLU_API_KEY não configurados no .env.');
  }
  $url = "{$base}/api/rest/v1/{$table}{$query}";
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'X-API-Key: ' . $apiKey,
    ],
    CURLOPT_TIMEOUT => 15,
  ]);
  if ($body !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
  }
  $response = curl_exec($ch);
  if (curl_errno($ch)) {
    $err = curl_error($ch);
    curl_close($ch);
    throw new RuntimeException("Falha de conexão com a Sou+Blu: {$err}");
  }
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $decoded = json_decode($response, true);
  if ($httpCode >= 400) {
    $msg = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? $response) : $response;
    throw new RuntimeException("Sou+Blu respondeu {$httpCode}: {$msg}");
  }
  return is_array($decoded) ? $decoded : [];
}

function soubluGenId(string $prefix): string {
  return $prefix . strtolower(base_convert((string) round(microtime(true) * 1000), 10, 36))
    . substr(bin2hex(random_bytes(4)), 0, 5);
}

function soubluTempPassword(): string {
  $chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
  $password = '';
  for ($i = 0; $i < 10; $i++) {
    $password .= $chars[random_int(0, strlen($chars) - 1)];
  }
  return $password;
}

/**
 * Cria o usuário (role=parceiro) + o registro em partners na Sou+Blu,
 * a partir de um cadastro aprovado da tabela local parceiros.
 * Retorna ['user_id' => ..., 'temp_password' => ...].
 * Lança RuntimeException se algo impedir a criação (ex.: e-mail já usado).
 */
function soubluCreateParceiro(array $record): array {
  $email = strtolower(trim($record['commercial_email']));
  if (!$email) {
    throw new RuntimeException('Cadastro sem e-mail comercial - não é possível criar o acesso.');
  }

  $existing = soubluRest('GET', 'users', null, '?email=eq.' . rawurlencode($email) . '&select=id&limit=1');
  if (!empty($existing)) {
    throw new RuntimeException("Já existe um usuário na Sou+Blu com o e-mail {$email} (id {$existing[0]['id']}). Ajuste manualmente.");
  }

  $digits = preg_replace('/\D/', '', $record['document'] ?? '');
  $fontedata = !empty($record['fontedata_result']) ? json_decode($record['fontedata_result'], true) : null;

  $tempPassword = soubluTempPassword();
  $userId = soubluGenId('u');
  $matricula = 'F' . random_int(10000, 99999);

  $userPayload = [
    'id' => $userId,
    'name' => $record['name'],
    'email' => $email,
    'password' => $tempPassword,
    'matricula' => $matricula,
    'department' => 'Parceiro',
    'role' => 'parceiro',
    'admin_id' => null,
    'balance' => 0,
    'points' => 0,
    'active' => true,
    'created_at' => date('c'),
  ];
  if (strlen($digits) === 11) {
    $userPayload['cpf'] = $digits;
  }
  if (!empty($record['commercial_phone'])) {
    $userPayload['phone'] = $record['commercial_phone'];
  }
  soubluRest('POST', 'users', $userPayload);

  $razaoSocial = $fontedata['razaoSocial'] ?? $record['name'];
  $endereco = '';
  if (!empty($fontedata['enderecos'][0])) {
    $e = $fontedata['enderecos'][0];
    $endereco = trim(implode(', ', array_filter([
      trim(($e['logradouro'] ?? '') . ' ' . ($e['numero'] ?? '')),
      $e['complemento'] ?? '',
      $e['bairro'] ?? '',
      trim(($e['cidade'] ?? '') . ($e['uf'] ? '/' . $e['uf'] : '')),
      $e['cep'] ?? '',
    ])));
  }

  $partnerPayload = [
    'id' => soubluGenId('prt'),
    'user_id' => $userId,
    'cnpj' => strlen($digits) === 14 ? $record['document'] : '',
    'razao_social' => $razaoSocial,
    'endereco' => $endereco,
    'contato' => $record['commercial_phone'] ?? '',
    'email' => $email,
    'active' => true,
    'created_at' => date('c'),
  ];
  soubluRest('POST', 'partners', $partnerPayload);

  return ['user_id' => $userId, 'temp_password' => $tempPassword];
}
