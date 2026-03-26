<?php
// send.php
// Recebe JSON via fetch e envia email para corporativo@interweg.com.br usando SMTP (Hostinger / Gmail / etc)

header("Content-Type: application/json; charset=utf-8");

// CORS (ajuste para seu domínio em produção)
$allowedOrigins = [
  "https://interweg.shop",
  "https://www.interweg.shop",
  "http://localhost:5500",
  "http://127.0.0.1:5500"
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : "";
if ($origin && in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Vary: Origin");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["ok" => false, "message" => "Método não permitido"]);
  exit;
}

// ====== Lê JSON ======
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data) {
  http_response_code(400);
  echo json_encode(["ok" => false, "message" => "JSON inválido"]);
  exit;
}

function s($v) {
  $v = is_string($v) ? $v : "";
  $v = trim($v);
  // sanitização simples
  $v = str_replace(["\r", "\n"], [" ", " "], $v);
  return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

$name = s($data["name"] ?? "");
$email = s($data["email"] ?? "");
$phone = s($data["phone"] ?? "");
$city = s($data["city"] ?? "");
$carModel = s($data["carModel"] ?? "");
$carYear = s($data["carYear"] ?? "");
$expectations = s($data["expectations"] ?? "");
$pageUrl = s($data["pageUrl"] ?? "");
$userAgent = s($data["userAgent"] ?? "");
$source = s($data["source"] ?? "landing_auto_premium");

if (!$name || !$email || !$phone || !$carModel) {
  http_response_code(400);
  echo json_encode(["ok" => false, "message" => "Campos obrigatórios: nome, email, whatsapp e modelo do carro."]);
  exit;
}

// ====== CONFIG SMTP ======
// IMPORTANTÍSSIMO: preencha com seus dados reais.
// Hostinger costuma ter SMTP do domínio (ex: mail.seudominio.com) OU você pode usar Gmail/Workspace com app password.

$SMTP_HOST = "smtp.hostinger.com";
$SMTP_PORT = 465;
$SMTP_USER = "corporativo@interweg.shop";
$SMTP_PASS = "interWeg123@";
$SMTP_SECURE = "ssl";

$MAIL_TO = "corporativo@interweg.shop";
$MAIL_FROM = "corporativo@interweg.shop";
$MAIL_FROM_NAME = "Interweg - Leads";
$REPLY_TO = $email;

// ====== Conteúdo do e-mail ======
$subject = "Lead Auto Premium — {$name} ({$carModel})";

$text = "Novo lead — Seguro Auto Premium\n\n"
  . "Nome: {$name}\n"
  . "Email: {$email}\n"
  . "WhatsApp: {$phone}\n"
  . "Cidade: " . ($city ?: "-") . "\n"
  . "Carro: {$carModel}\n"
  . "Ano: " . ($carYear ?: "-") . "\n\n"
  . "Expectativas:\n" . ($expectations ?: "-") . "\n\n"
  . "Origem: {$source}\n"
  . "Página: " . ($pageUrl ?: "-") . "\n"
  . "User-Agent: " . ($userAgent ?: "-") . "\n"
  . "Recebido em: " . date("c") . "\n";

$html = "
  <div style='font-family:Arial,sans-serif;line-height:1.5;color:#0b1220'>
    <h2 style='margin:0 0 10px'>Novo lead — Seguro Auto Premium</h2>
    <table cellpadding='6' cellspacing='0' style='border-collapse:collapse'>
      <tr><td><b>Nome</b></td><td>{$name}</td></tr>
      <tr><td><b>Email</b></td><td>{$email}</td></tr>
      <tr><td><b>WhatsApp</b></td><td>{$phone}</td></tr>
      <tr><td><b>Cidade</b></td><td>" . ($city ?: "-") . "</td></tr>
      <tr><td><b>Carro</b></td><td>{$carModel}</td></tr>
      <tr><td><b>Ano</b></td><td>" . ($carYear ?: "-") . "</td></tr>
    </table>
    <p><b>Expectativas</b><br/>" . nl2br($expectations ?: "-") . "</p>
    <hr style='border:none;border-top:1px solid #eee;margin:14px 0'/>
    <p style='font-size:12px;color:#5b677a;margin:0'>
      Origem: {$source}<br/>
      Página: " . ($pageUrl ?: "-") . "<br/>
      User-Agent: " . ($userAgent ?: "-") . "<br/>
      Recebido em: " . date("c") . "
    </p>
  </div>
";

// ====== PHPMailer (sem composer): baixa e inclui libs ======
$phpmailerPath = __DIR__ . "/phpmailer/src/";
require_once $phpmailerPath . "Exception.php";
require_once $phpmailerPath . "PHPMailer.php";
require_once $phpmailerPath . "SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = $SMTP_HOST;
  $mail->SMTPAuth = true;
  $mail->Username = $SMTP_USER;
  $mail->Password = $SMTP_PASS;
  $mail->SMTPSecure = $SMTP_SECURE; // 'tls' ou 'ssl'
  $mail->Port = $SMTP_PORT;

  $mail->CharSet = "UTF-8";
  $mail->setFrom($MAIL_FROM, $MAIL_FROM_NAME);
  $mail->addAddress($MAIL_TO);
  $mail->addReplyTo($REPLY_TO);

  $mail->isHTML(true);
  $mail->Subject = $subject;
  $mail->Body = $html;
  $mail->AltBody = $text;

  $mail->send();

  echo json_encode(["ok" => true]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "message" => "Falha ao enviar e-mail.",
    "debug" => $mail->ErrorInfo
  ]);
}