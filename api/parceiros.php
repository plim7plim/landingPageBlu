<?php
// Endpoint PHP (Locaweb/cPanel). Guarda os cadastros de parceiro no MySQL e
// permite listar e mudar o status deles depois. Rode schema.sql uma vez no
// phpMyAdmin antes de usar.
//
// POST  : recebe um cadastro (chamado pelo próprio formulário do site, público).
// GET   : lista todos os cadastros - só libera com o header "X-Admin-Token"
//         igual ao ADMIN_TOKEN configurado, para os dados dos parceiros
//         (CPF/CNPJ, contatos) nunca ficarem públicos.
// PATCH : atualiza o status (pendente/aprovado/recusado) de um cadastro -
//         também protegido pelo mesmo token.

require __DIR__ . '/../config.php';
require __DIR__ . '/lib/mailer.php';
require __DIR__ . '/lib/soublu.php';

const STATUSES = ['pendente', 'aprovado', 'recusado'];

function recordFromRow(array $row): array {
  return [
    'id' => $row['id'],
    'createdAt' => $row['created_at'],
    'name' => $row['name'],
    'document' => $row['document'],
    'commercialPhone' => $row['commercial_phone'],
    'whatsappPhone' => $row['whatsapp_phone'],
    'commercialEmail' => $row['commercial_email'],
    'financialEmail' => $row['financial_email'],
    'city' => $row['city'],
    'contactTime' => $row['contact_time'],
    'worksCommercial' => $row['works_commercial'],
    'hasAddress' => $row['has_address'],
    'creditExperience' => $row['credit_experience'],
    'message' => $row['message'],
    'status' => $row['status'],
    'fontedataResult' => !empty($row['fontedata_result']) ? json_decode($row['fontedata_result'], true) : null,
    'fontedataConsultadoEm' => $row['fontedata_consultado_em'],
    'soubluUserId' => $row['soublu_user_id'],
    'soubluTempPassword' => $row['soublu_temp_password'],
    'soubluSyncedAt' => $row['soublu_synced_at'],
    'soubluError' => $row['soublu_error'],
  ];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
  $data = jsonInput();
  if (empty($data['name']) || empty($data['document']) || empty($data['commercialPhone']) || empty($data['commercialEmail'])) {
    jsonResponse(['ok' => false, 'error' => 'Dados obrigatórios ausentes.'], 400);
  }
  try {
    $stmt = db()->prepare('INSERT INTO parceiros
      (id, created_at, name, document, commercial_phone, whatsapp_phone, commercial_email, financial_email, city, contact_time, works_commercial, has_address, credit_experience, message, status)
      VALUES (:id, :created_at, :name, :document, :commercial_phone, :whatsapp_phone, :commercial_email, :financial_email, :city, :contact_time, :works_commercial, :has_address, :credit_experience, :message, "pendente")');
    $stmt->execute([
      'id' => $data['id'] ?? (string) round(microtime(true) * 1000),
      'created_at' => $data['createdAt'] ?? date('c'),
      'name' => $data['name'],
      'document' => $data['document'],
      'commercial_phone' => $data['commercialPhone'],
      'whatsapp_phone' => $data['whatsappPhone'] ?? '',
      'commercial_email' => $data['commercialEmail'],
      'financial_email' => $data['financialEmail'] ?? '',
      'city' => $data['city'] ?? '',
      'contact_time' => $data['contactTime'] ?? '',
      'works_commercial' => $data['worksCommercial'] ?? '',
      'has_address' => $data['hasAddress'] ?? '',
      'credit_experience' => $data['creditExperience'] ?? '',
      'message' => $data['message'] ?? '',
    ]);
    jsonResponse(['ok' => true], 201);
  } catch (Exception $e) {
    error_log('Erro ao salvar cadastro de parceiro: ' . $e->getMessage());
    jsonResponse(['ok' => false], 500);
  }
}

if ($method === 'GET') {
  requireAdmin();
  try {
    $rows = db()->query('SELECT * FROM parceiros ORDER BY id DESC')->fetchAll();
    jsonResponse(['ok' => true, 'records' => array_map('recordFromRow', $rows)]);
  } catch (Exception $e) {
    error_log('Erro ao listar cadastros de parceiro: ' . $e->getMessage());
    jsonResponse(['ok' => false], 500);
  }
}

if ($method === 'PATCH') {
  requireAdmin();
  $data = jsonInput();
  $id = $data['id'] ?? null;
  $status = $data['status'] ?? null;
  if (!$id || !in_array($status, STATUSES, true)) {
    jsonResponse(['ok' => false, 'error' => 'id e status (pendente/aprovado/recusado) são obrigatórios.'], 400);
  }
  try {
    $stmt = db()->prepare('UPDATE parceiros SET status = :status, status_updated_at = :updated WHERE id = :id');
    $stmt->execute(['status' => $status, 'updated' => date('c'), 'id' => $id]);
    if ($stmt->rowCount() === 0) {
      jsonResponse(['ok' => false, 'error' => 'Cadastro não encontrado.'], 404);
    }

    $soublu = null;
    if ($status === 'aprovado') {
      $soublu = syncParceiroToSoublu($id);
    }

    jsonResponse(['ok' => true, 'soublu' => $soublu]);
  } catch (Exception $e) {
    error_log('Erro ao atualizar status do cadastro: ' . $e->getMessage());
    jsonResponse(['ok' => false], 500);
  }
}

/**
 * Cria o usuário/parceiro na Sou+Blu para este cadastro, se ainda não existir.
 * Nunca lança - grava o erro em soublu_error e devolve ['ok'=>false,'error'=>...]
 * para não impedir a aprovação em si.
 */
function syncParceiroToSoublu(string $id): array {
  $stmt = db()->prepare('SELECT * FROM parceiros WHERE id = :id');
  $stmt->execute(['id' => $id]);
  $record = $stmt->fetch();
  if (!$record) {
    return ['ok' => false, 'error' => 'Cadastro não encontrado ao sincronizar.'];
  }
  if (!empty($record['soublu_user_id'])) {
    return ['ok' => true, 'userId' => $record['soublu_user_id'], 'alreadyLinked' => true];
  }

  try {
    $result = soubluCreateParceiro($record);
    $upd = db()->prepare('UPDATE parceiros SET soublu_user_id = :uid, soublu_temp_password = :pwd, soublu_synced_at = :synced, soublu_error = NULL WHERE id = :id');
    $upd->execute([
      'uid' => $result['user_id'],
      'pwd' => $result['temp_password'],
      'synced' => date('c'),
      'id' => $id,
    ]);
    return ['ok' => true, 'userId' => $result['user_id'], 'tempPassword' => $result['temp_password']];
  } catch (Throwable $e) {
    error_log('Erro ao criar parceiro na Sou+Blu: ' . $e->getMessage());
    $upd = db()->prepare('UPDATE parceiros SET soublu_error = :err WHERE id = :id');
    $upd->execute(['err' => $e->getMessage(), 'id' => $id]);
    return ['ok' => false, 'error' => $e->getMessage()];
  }
}

header('Allow: GET, POST, PATCH');
jsonResponse(['ok' => false], 405);
