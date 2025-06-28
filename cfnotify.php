<?php
$envPath = '/home/iruzic/.env';
if (!file_exists($envPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Encryption key file missing']);
    exit;
}

$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) continue; // skip comments
    list($key, $value) = explode('=', $line, 2);
    $env[trim($key)] = trim($value);
}

if (empty($env['CF_ENCRYPTION_KEY'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Missing encryption key in .env']);
    exit;
}

$encryptionKeyB64 = $env['CF_ENCRYPTION_KEY'];

function decryptAESGCM($key_b64, $iv_b64, $ciphertext_b64) {
    $key = base64_decode($key_b64);
    $iv = base64_decode($iv_b64);
    $ciphertext = base64_decode($ciphertext_b64);

    $tag = substr($ciphertext, -16);
    $ciphertextWithoutTag = substr($ciphertext, 0, -16);

    return openssl_decrypt(
        $ciphertextWithoutTag,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['iv']) || !isset($input['encryptedData'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid encrypted payload']);
    exit;
}

$decryptedJson = decryptAESGCM($encryptionKeyB64, $input['iv'], $input['encryptedData']);
if (!$decryptedJson) {
    http_response_code(400);
    echo json_encode(['error' => 'Decryption failed']);
    exit;
}

$data = json_decode($decryptedJson, true);
if (!$data || !isset($data['name'], $data['email'], $data['subject'], $data['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Decrypted data invalid']);
    exit;
}

$name = filter_var($data['name'], FILTER_SANITIZE_STRING);
$email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
$subject = filter_var($data['subject'], FILTER_SANITIZE_STRING);
$message = filter_var($data['message'], FILTER_SANITIZE_STRING);

if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

$to = "contact@ilijaruzic.rs";
$headers = "From: {$name} <{$email}>\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

if (mail($to, $subject, $message, $headers)) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send email']);
}
