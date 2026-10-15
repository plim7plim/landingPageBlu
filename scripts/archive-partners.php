<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
require __DIR__ . '/../config.php';
require __DIR__ . '/../api/lib/partner-archive.php';
try {
  echo 'Cadastros arquivados: ' . archiveExpiredPartners(db()) . PHP_EOL;
} catch (Throwable $error) {
  error_log('Falha no arquivamento de parceiros: ' . $error->getMessage());
  exit(1);
}
