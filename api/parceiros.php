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
require __DIR__ . '/lib/admin-actions.php';

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
    'archivedAt' => $row['archived_at'] ?? null,
    'statusUpdatedAt' => $row['status_updated_at'] ?? null,
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
    $stmt = db()->prepare('INSERT INTO lp_parceiros
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
  header('Cache-Control: no-store');
  if (($_GET['action'] ?? '') === 'approval-challenge') {
    $id = $_GET['id'] ?? '';
    if (!is_string($id) || !ctype_digit($id)) jsonResponse(['ok' => false, 'error' => 'Cadastro inválido.'], 400);
    try {
      $stmt = db()->prepare('SELECT * FROM lp_parceiros WHERE id = :id');
      $stmt->execute(['id' => $id]);
      $record = $stmt->fetch();
      if (!$record) jsonResponse(['ok' => false, 'error' => 'Cadastro não encontrado.'], 404);
      if (!empty($record['archived_at']) || !empty($record['soublu_user_id'])) {
        jsonResponse(['ok' => false, 'error' => 'Atualize a lista: este cadastro está arquivado ou já possui acesso.'], 409);
      }
      jsonResponse(['ok' => true, 'challenge' => issueApprovalChallenge($record)]);
    } catch (Throwable $e) {
      error_log('Falha ao preparar confirmação: ' . $e->getMessage());
      jsonResponse(['ok' => false, 'error' => 'Não foi possível gerar a confirmação.'], 500);
    }
  }
  try {
    $rows = db()->query('SELECT * FROM lp_parceiros ORDER BY id DESC')->fetchAll();
    jsonResponse([
      'ok' => true,
      'records' => array_map('recordFromRow', $rows),
      'loginUrl' => env('SOUBLU_LOGIN_URL', 'https://soumaisblu.com.br/index.html'),
    ]);
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

  if (($data['action'] ?? null) === 'resend-email') {
    if (!$id) {
      jsonResponse(['ok' => false, 'error' => 'id é obrigatório.'], 400);
    }
    try {
      $stmt = db()->prepare('SELECT * FROM lp_parceiros WHERE id = :id');
      $stmt->execute(['id' => $id]);
      $record = $stmt->fetch();
      if (!$record) {
        jsonResponse(['ok' => false, 'error' => 'Cadastro não encontrado.'], 404);
      }
      if (empty($record['soublu_user_id']) || empty($record['soublu_temp_password'])) {
        jsonResponse(['ok' => false, 'error' => 'Este cadastro ainda não tem acesso criado na Sou+Blu.'], 409);
      }
      $email = strtolower(trim($record['commercial_email']));
      $sent = sendApprovalEmail($record, $email, $record['soublu_temp_password']);
      jsonResponse(['ok' => true, 'emailSent' => $sent]);
    } catch (Exception $e) {
      error_log('Erro ao reenviar e-mail de aprovação: ' . $e->getMessage());
      jsonResponse(['ok' => false], 500);
    }
  }

  $action = $data['action'] ?? 'status';
  if (!is_scalar($id) || !ctype_digit((string) $id) || !in_array($action, ['status', 'archive', 'restore'], true)
      || ($action === 'status' && !in_array($status, STATUSES, true))) {
    jsonResponse(['ok' => false, 'error' => 'Cadastro ou ação inválidos.'], 400);
  }
  $pdo = null;
  try {
    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM lp_parceiros WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $id]);
    $record = $stmt->fetch();
    if (!$record) {
      $pdo->rollBack();
      jsonResponse(['ok' => false, 'error' => 'Cadastro não encontrado.'], 404);
    }
    if (!array_key_exists('expectedStatus', $data) || !array_key_exists('expectedArchivedAt', $data)
        || !array_key_exists('expectedUpdatedAt', $data)
        || $data['expectedStatus'] !== $record['status']
        || $data['expectedArchivedAt'] !== ($record['archived_at'] ?? null)
        || $data['expectedUpdatedAt'] !== ($record['status_updated_at'] ?? null)) {
      $pdo->rollBack();
      jsonResponse(['ok' => false, 'error' => 'O cadastro foi atualizado. Recarregue a lista antes de continuar.'], 409);
    }
    if ($action !== 'status') {
      if (!array_key_exists('archived_at', $record)) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Arquivamento indisponível: aplique a migração 001_partner_archive.sql.'], 503);
      }
      $stmt = $pdo->prepare('UPDATE lp_parceiros SET archived_at = :archived WHERE id = :id');
      $stmt->execute(['id' => $id, 'archived' => $action === 'archive' ? date('c') : null]);
      $soublu = null;
    } else {
      if (!empty($record['archived_at'])) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Restaure o cadastro antes de alterar a situação.'], 409);
      }
      if ($status === 'aprovado' && empty($record['soublu_user_id']) && !consumeApprovalChallenge($record, $data)) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Confirmação inválida ou expirada. Abra uma nova confirmação.'], 409);
      }
      $stmt = $pdo->prepare('UPDATE lp_parceiros SET status = :status, status_updated_at = :updated WHERE id = :id');
      $stmt->execute(['status' => $status, 'updated' => date('c'), 'id' => $id]);
      $soublu = $status === 'aprovado' ? syncParceiroToSoublu((string) $id) : null;
    }
    $stmt = $pdo->prepare('SELECT * FROM lp_parceiros WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $updated = $stmt->fetch();
    $pdo->commit();
    jsonResponse(['ok' => true, 'record' => recordFromRow($updated), 'soublu' => $soublu]);
  } catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Erro ao atualizar cadastro: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Não foi possível salvar a alteração. Atualize a lista antes de tentar novamente.'], 500);
  }
}

/**
 * Cria o usuário/parceiro na Sou+Blu para este cadastro, se ainda não existir.
 * Nunca lança - grava o erro em soublu_error e devolve ['ok'=>false,'error'=>...]
 * para não impedir a aprovação em si.
 */
function syncParceiroToSoublu(string $id): array {
  $stmt = db()->prepare('SELECT * FROM lp_parceiros WHERE id = :id');
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
    $upd = db()->prepare('UPDATE lp_parceiros SET soublu_user_id = :uid, soublu_temp_password = :pwd, soublu_synced_at = :synced, soublu_error = NULL WHERE id = :id');
    $upd->execute([
      'uid' => $result['user_id'],
      'pwd' => $result['temp_password'],
      'synced' => date('c'),
      'id' => $id,
    ]);
    return [
      'ok' => true,
      'userId' => $result['user_id'],
      'tempPassword' => $result['temp_password'],
      'emailSent' => $result['email_sent'],
    ];
  } catch (Throwable $e) {
    error_log('Erro ao criar parceiro na Sou+Blu: ' . $e->getMessage());
    $upd = db()->prepare('UPDATE lp_parceiros SET soublu_error = :err WHERE id = :id');
    $upd->execute(['err' => $e->getMessage(), 'id' => $id]);
    return ['ok' => false, 'error' => $e->getMessage()];
  }
}

header('Allow: GET, POST, PATCH');
jsonResponse(['ok' => false], 405);
