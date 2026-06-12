<?php
// ── Ley Studio · Endpoint de correo ──
// Envía correos desde hola@leystudiohn.com. Solo usuarias autenticadas (Firebase Auth).
header('Content-Type: application/json; charset=utf-8');

$FIREBASE_API_KEY = 'AIzaSyC85_lPNWwYGHupPEL8V6hxS3bWGHRW_6E';
$CORREOS_PERMITIDOS = ['leonardoalvarado.jm@gmail.com', 'leylan.perdomo@gmail.com'];
$REMITENTE = 'hola@leystudiohn.com';
$REMITENTE_NOMBRE = 'Ley Studio';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"error":"metodo"}'; exit; }

$in = json_decode(file_get_contents('php://input'), true);
if (!$in) { http_response_code(400); echo '{"error":"json"}'; exit; }

$idToken = $in['idToken'] ?? '';
$to      = trim($in['to'] ?? '');
$subject = trim($in['subject'] ?? '');
$html    = $in['html'] ?? '';
$att     = $in['attachment'] ?? null;

if (!$idToken || !filter_var($to, FILTER_VALIDATE_EMAIL) || !$subject || !$html) {
  http_response_code(400); echo '{"error":"campos incompletos"}'; exit;
}

// ── 1) Verificar sesión de Firebase ──
$ch = curl_init('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . $FIREBASE_API_KEY);
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode(['idToken' => $idToken]),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 10,
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$u = json_decode($res, true);
$emailAuth = strtolower($u['users'][0]['email'] ?? '');
if ($code !== 200 || !$emailAuth || !in_array($emailAuth, $CORREOS_PERMITIDOS)) {
  http_response_code(401); echo '{"error":"sesion invalida"}'; exit;
}

// ── 2) Construir y enviar ──
$subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$fromEnc = '=?UTF-8?B?' . base64_encode($REMITENTE_NOMBRE) . '?= <' . $REMITENTE . '>';
$headers = "From: $fromEnc\r\nReply-To: $REMITENTE\r\nMIME-Version: 1.0\r\n";

if ($att && !empty($att['data']) && !empty($att['name'])) {
  $data = base64_decode($att['data'], true);
  if ($data === false || strlen($data) > 4 * 1024 * 1024) { http_response_code(400); echo '{"error":"adjunto invalido"}'; exit; }
  $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $att['name']);
  $boundary = 'b' . md5(uniqid('', true));
  $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
  $body  = "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n";
  $body .= "--$boundary\r\nContent-Type: application/pdf; name=\"$name\"\r\nContent-Disposition: attachment; filename=\"$name\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($data)) . "\r\n--$boundary--";
} else {
  $headers .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
  $body = chunk_split(base64_encode($html));
}

$ok = mail($to, $subjectEnc, $body, $headers, '-f' . $REMITENTE);
echo $ok ? '{"ok":true}' : '{"error":"el servidor no pudo enviar"}';
