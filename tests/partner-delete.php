<?php
// Teste do endpoint em uma tabela temporária: nenhum registro real é alterado.
if (!in_array('--mysql-temp', $argv, true)) {
  echo "Execute com --mysql-temp para testar em tabela temporária isolada.\n";
  exit;
}
require __DIR__ . '/../config.php';
$scenario = $argv[2] ?? 'delete';
$pdo = db();
$schema = file_get_contents(__DIR__ . '/../schema.sql');
$schema = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $schema);
$pdo->exec($schema); // Se falhar, interrompe antes de qualquer alteração.
$status = $scenario === 'wrong-status' ? 'pendente' : 'recusado';
$stmt = $pdo->prepare('INSERT INTO lp_parceiros (id,created_at,name,document,commercial_phone,commercial_email,status) VALUES (?,?,?,?,?,?,?)');
$stmt->execute([1, date('c'), 'Teste isolado', '000', '000', 'test@example.invalid', $status]);
$input = ['action' => 'delete', 'id' => '1', 'confirmDelete' => $scenario !== 'unconfirmed',
  'expectedStatus' => $scenario === 'stale' ? 'aprovado' : $status,
  'expectedArchivedAt' => null, 'expectedUpdatedAt' => null];
register_shutdown_function(function () use ($pdo, $scenario) {
  $count = (int) $pdo->query('SELECT COUNT(*) FROM lp_parceiros')->fetchColumn();
  $expected = $scenario === 'delete' ? 0 : 1;
  if ($count !== $expected) {
    fwrite(STDERR, "FALHA: $scenario\n");
    exit(1);
  }
  echo "\nOK: $scenario; somente tabela temporária.\n";
});
$_SERVER['REQUEST_METHOD'] = 'PATCH';
$_SERVER['HTTP_X_ADMIN_TOKEN'] = env('ADMIN_TOKEN');
$source = file_get_contents(__DIR__ . '/../api/parceiros.php');
$source = str_replace("require __DIR__ . '/../config.php';", '', $source);
$source = str_replace('__DIR__', var_export(realpath(__DIR__ . '/../api'), true), $source);
$source = str_replace('$data = jsonInput();', '$data = $input;', $source);
eval('?>' . $source);
