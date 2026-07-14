<?php
// Email pour les questionnaires commerciaux — sans CC_STATIC
function sendCommercialEmail(string $to, string $subject, array $emailData, array $cc = []): bool
{
    $html = generateEmailTemplate($emailData);

    $headersArr = [
        'From: CFAO Mobility Academy <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ];
    if (!empty($cc)) {
        $headersArr[] = 'Cc: ' . implode(', ', $cc);
    }

    return mail($to, $subject, $html, implode("\r\n", $headersArr));
}

// Email avec pièce jointe Excel
function sendCommercialEmailWithExcel(string $to, string $subject, array $emailData, string $filePath, string $fileName): bool
{
    $html     = generateEmailTemplate($emailData);
    $boundary = '----=_Part_' . md5(uniqid());

    $fileContent = file_get_contents($filePath);
    if ($fileContent === false) {
        return sendCommercialEmail($to, $subject, $emailData);
    }
    $fileEncoded = chunk_split(base64_encode($fileContent));
    $mimeType    = substr($fileName, -4) === '.zip' ? 'application/zip' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $html . "\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: {$mimeType}; name=\"{$fileName}\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n";
    $body .= $fileEncoded . "\r\n";
    $body .= "--{$boundary}--";

    $headers = implode("\r\n", [
        'From: CFAO Mobility Academy <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        'X-Mailer: PHP/' . phpversion(),
    ]);

    return mail($to, $subject, $body, $headers);
}
