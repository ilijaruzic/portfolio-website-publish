<?php
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['subject']) || !isset($data['body'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$to = "contact@ilijaruzic.rs";
$subject = $data['subject'];
$message = $data['body'];

$headers = "From: noreply@ilijaruzic.rs\r\n" .
           "Reply-To: noreply@ilijaruzic.rs\r\n" .
           "Content-Type: text/plain; charset=UTF-8\r\n";

$mailSent = mail($to, $subject, $message, $headers);

if ($mailSent) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send email']);
}
?>
