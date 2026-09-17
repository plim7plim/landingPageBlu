<?php
// Endpoint PHP (Locaweb/cPanel). Roda só no servidor - a FONTEDATA_API_KEY
// nunca é enviada ao navegador. O resultado da consulta NUNCA volta na
// resposta HTTP: é gravado na própria linha do parceiro (colunas
// fontedata_result / fontedata_consultado_em) e, opcionalmente, também
// encaminhado para o canal interno (INTERNAL_NOTIFY_WEBHOOK_URL) ou
// registrado no log de erros do PHP.

require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Allow: POST');
  jsonResponse(['ok' => false], 405);
}

$input = jsonInput();
$id = $input['id'] ?? null;
$digits = preg_replace('/\D/', '', $input['cnpj'] ?? '');
if (strlen($digits) !== 14) {
  jsonResponse(['ok' => false, 'error' => 'CNPJ inválido.'], 400);
}

$apiKey = env('FONTEDATA_API_KEY');
if (!$apiKey) {
  error_log('FONTEDATA_API_KEY não configurada - consulta de CNPJ ignorada.');
  jsonResponse(['ok' => true]);
}

$ch = curl_init("https://app.fontedata.com/api/v1/consulta/cadastro-pj-plus?cnpj={$digits}");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ["X-API-Key: {$apiKey}"],
  CURLOPT_TIMEOUT => 15,
]);
$response = curl_exec($ch);
if (curl_errno($ch)) {
  error_log('Erro ao consultar FonteData: ' . curl_error($ch));
  curl_close($ch);
  jsonResponse(['ok' => true]);
}
curl_close($ch);
$companyData = json_decode($response, true);
$consultadoEm = date('c');

if ($id) {
  try {
    $stmt = db()->prepare('UPDATE parceiros SET fontedata_result = :result, fontedata_consultado_em = :consultado_em WHERE id = :id');
    $stmt->execute([
      'result' => json_encode($companyData),
      'consultado_em' => $consultadoEm,
      'id' => $id,
    ]);
  } catch (Exception $e) {
    error_log('Erro ao salvar resultado da consulta CNPJ: ' . $e->getMessage());
  }
}

$payload = [
  'consultadoEm' => $consultadoEm,
  'cnpj' => $digits,
  'parceiro' => $input['partnerName'] ?? null,
  'fonteData' => $companyData,
];

$webhook = env('INTERNAL_NOTIFY_WEBHOOK_URL');
if ($webhook) {
  $ch2 = curl_init($webhook);
  curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 10,
  ]);
  curl_exec($ch2);
  curl_close($ch2);
} else {
  // Sem webhook configurado: fica registrado no log de erros do PHP.
  error_log('Consulta CNPJ (FonteData): ' . json_encode($payload));
}

// Resposta genérica - nunca inclui os dados da consulta.
jsonResponse(['ok' => true]);
