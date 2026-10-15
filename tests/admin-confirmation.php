<?php
// Teste isolado: não usa banco, API externa, credenciais reais ou envio de e-mail.
require __DIR__ . '/../api/lib/admin-actions.php';
$admin = 'test-admin';
$destination = 'https://example.invalid/login';
function adminHeaderToken(): string { return $GLOBALS['admin']; }
function env(string $key, $fallback = null) {
  return $key === 'SOUBLU_LOGIN_URL' ? $GLOBALS['destination'] : $fallback;
}
function check(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}
$record = ['id' => '123', 'name' => 'Teste', 'commercial_email' => 'test@example.invalid', 'status' => 'pendente'];
$c = issueApprovalChallenge($record);
$input = ['confirmationToken' => $c['confirmationToken'], 'confirmationCode' => $c['code']];
check(consumeApprovalChallenge($record, $input), 'Código válido deve ser aceito.');
check(!consumeApprovalChallenge($record, $input), 'Replay deve ser recusado.');
$c = issueApprovalChallenge($record);
check(!consumeApprovalChallenge($record, ['confirmationToken' => $c['confirmationToken'], 'confirmationCode' => 'wrong']), 'Código incorreto deve ser recusado.');
$c = issueApprovalChallenge($record);
$changed = $record; $changed['commercial_email'] = 'other@example.invalid';
check(!consumeApprovalChallenge($changed, ['confirmationToken' => $c['confirmationToken'], 'confirmationCode' => $c['code']]), 'Cadastro alterado deve invalidar o código.');
$c = issueApprovalChallenge($record);
$destination = 'https://other.invalid/login';
check(!consumeApprovalChallenge($record, ['confirmationToken' => $c['confirmationToken'], 'confirmationCode' => $c['code']]), 'Destino alterado deve invalidar o código.');
$c = issueApprovalChallenge($record);
$admin = 'other-admin';
check(!consumeApprovalChallenge($record, ['confirmationToken' => $c['confirmationToken'], 'confirmationCode' => $c['code']]), 'Outro token não pode consumir a confirmação.');
$c = issueApprovalChallenge($record);
approvalSession(); $_SESSION['approval_challenges'][$c['confirmationToken']]['expires'] = time() - 1; session_write_close();
check(!consumeApprovalChallenge($record, ['confirmationToken' => $c['confirmationToken'], 'confirmationCode' => $c['code']]), 'Código expirado deve ser recusado.');
echo "OK: válido, replay, erro, alteração de cadastro/destino/admin e expiração.\n";
