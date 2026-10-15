<?php
require __DIR__ . '/../api/lib/partner-archive.php';

function archiveCheck(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$now = new DateTimeImmutable('2026-10-15T12:00:00-03:00');
$record = ['status' => 'aprovado', 'status_updated_at' => '2026-10-12T12:00:00-03:00', 'archived_at' => null];
archiveCheck(partnerArchiveDueAt($record) == $now, 'O prazo deve completar exatamente 72 horas.');
archiveCheck(partnerArchiveDueAt($record) > $now->modify('-1 second'), 'Não pode arquivar antes das 72 horas.');
$record['status'] = 'recusado';
archiveCheck(partnerArchiveDueAt($record) == $now, 'Excluídos têm o mesmo prazo.');
foreach (['pendente', 'em_negociacao'] as $status) {
  $record['status'] = $status;
  archiveCheck(partnerArchiveDueAt($record) === null, 'Situações em acompanhamento não expiram.');
}
$record['status'] = 'aprovado';
$record['archived_at'] = $now->format(DATE_ATOM);
archiveCheck(partnerArchiveDueAt($record) === null, 'Arquivado não deve receber novo prazo.');
$record['archived_at'] = null;
$record['status_updated_at'] = $now->format(DATE_ATOM);
archiveCheck(partnerArchiveDueAt($record) == $now->modify('+72 hours'), 'Restaurar deve reiniciar o prazo.');
$record['status_updated_at'] = null;
archiveCheck(partnerArchiveDueAt($record) === null, 'Cadastro sem data precisa ser inicializado.');
$record['status_updated_at'] = 'invalid';
archiveCheck(partnerArchiveDueAt($record) === null, 'Data inválida não pode arquivar um cadastro.');

if (in_array('--mysql-temp', $argv, true)) {
  require __DIR__ . '/../config.php';
  $pdo = db();
  // A tabela temporária desta conexão oculta a real, sem modificar seus registros.
  // Se CREATE falhar, o teste para antes de executar qualquer atualização.
  $pdo->exec('CREATE TEMPORARY TABLE lp_parceiros (id BIGINT PRIMARY KEY, status VARCHAR(16), status_updated_at VARCHAR(40), archived_at VARCHAR(40))');
  $insert = $pdo->prepare('INSERT INTO lp_parceiros VALUES (?, ?, ?, ?)');
  $insert->execute([1, 'aprovado', '2026-10-12T12:00:00-03:00', null]);
  $insert->execute([2, 'recusado', '2026-10-12T11:59:59-03:00', null]);
  $insert->execute([3, 'aprovado', '2026-10-12T12:00:01-03:00', null]);
  $insert->execute([4, 'em_negociacao', '2026-10-01T12:00:00-03:00', null]);
  $insert->execute([5, 'aprovado', null, null]);
  $insert->execute([6, 'recusado', '2026-10-01T12:00:00-03:00', '2026-10-10T12:00:00-03:00']);
  archiveCheck(archiveExpiredPartners($pdo, $now) === 2, 'Somente os dois vencidos devem ser arquivados.');
  archiveCheck(archiveExpiredPartners($pdo, $now) === 0, 'Executar novamente não deve repetir o arquivamento.');
  $rows = $pdo->query('SELECT * FROM lp_parceiros ORDER BY id')->fetchAll();
  archiveCheck($rows[2]['archived_at'] === null && $rows[3]['archived_at'] === null, 'Preservar cadastros ainda em acompanhamento.');
  archiveCheck($rows[4]['status_updated_at'] === $now->format(DATE_ATOM), 'Inicializar prazo legado.');
  $pdo->exec("UPDATE lp_parceiros SET archived_at = NULL, status_updated_at = '2026-10-15T12:00:00-03:00' WHERE id = 1");
  archiveCheck(archiveExpiredPartners($pdo, $now) === 0, 'Restaurado não deve arquivar imediatamente.');
  $pdo->exec('DROP TEMPORARY TABLE lp_parceiros');
}
echo "OK: prazo, situações, restauração, datas inválidas e arquivamento.\n";
