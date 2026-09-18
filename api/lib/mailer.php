<?php
// Envio do e-mail automático de boas-vindas quando um parceiro é aprovado e
// tem o acesso criado na Sou+Blu (ver soubluCreateParceiro em soublu.php).
// Usa a função mail() nativa do PHP - funciona direto na Locaweb sem
// precisar de conta SMTP separada. Nunca lança: falha de envio só é
// registrada no log de erros, para não impedir a aprovação do cadastro.

function sendApprovalEmail(array $record, string $email, string $tempPassword): bool {
  try {
    $name = trim($record['name'] ?? '');
    $loginUrl = env('SOUBLU_LOGIN_URL', 'https://soumaisblu.com.br/index.html');
    $from = env('MAIL_FROM', 'SOU + BLU <naoresponda@blupromotora.com.br>');

    $subject = 'Seu acesso à Sou+Blu foi criado';
    $body = "Olá, {$name}!\n\n"
      . "Seu cadastro de parceiro foi aprovado e o acesso à plataforma Sou+Blu já está disponível.\n\n"
      . "Login: {$email}\n"
      . "Senha temporária: {$tempPassword}\n\n"
      . "Acesse em: {$loginUrl}\n\n"
      . "Por segurança, troque essa senha no primeiro acesso e nunca a compartilhe com terceiros.\n\n"
      . "Equipe SOU + BLU\n";

    $headers = "MIME-Version: 1.0\r\n"
      . "Content-Type: text/plain; charset=UTF-8\r\n"
      . "From: {$from}\r\n";

    $encodedSubject = function_exists('mb_encode_mimeheader')
      ? mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n")
      : '=?UTF-8?B?' . base64_encode($subject) . '?=';

    return @mail($email, $encodedSubject, $body, $headers);
  } catch (Throwable $e) {
    error_log('Erro ao enviar e-mail de aprovação para ' . $email . ': ' . $e->getMessage());
    return false;
  }
}
