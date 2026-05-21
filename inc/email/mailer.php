<?php
declare(strict_types=1);

/**
 * Sends confirmation email to participant via SMTP.
 * Uses raw PHP sockets — no external dependencies.
 */
function send_confirmation_email(
    string $to,
    array  $campaign,
    string $lang,
    int    $score,
    int    $total,
    int    $pct,
    string $campaignLink
): bool {
    $cfg = app_config()['smtp'];

    if ($cfg['host'] === '' || $cfg['from'] === '') {
        if (!empty(app_config()['debug'])) {
            error_log("SMTP not configured: skipping email to {$to}");
        }
        return false;
    }

    $subject = build_email_subject($campaign, $lang, $score, $total);
    $body    = build_email_body($campaign, $lang, $score, $total, $pct, $campaignLink);

    return smtp_send(
        host:     $cfg['host'],
        port:     $cfg['port'],
        user:     $cfg['user'],
        pass:     $cfg['pass'],
        from:     $cfg['from'],
        fromName: $cfg['from_name'],
        to:       $to,
        subject:  $subject,
        body:     $body
    );
}

function build_email_subject(array $campaign, string $lang, int $score, int $total): string
{
    $tpl = (array) ($campaign['email_confirmation']['subject'] ?? []);
    $raw = trim((string) ($tpl[$lang] ?? $tpl['es'] ?? 'Tu participación en el sorteo'));
    return str_replace(['{score}', '{total}'], [$score, $total], $raw);
}

function build_email_body(array $campaign, string $lang, int $score, int $total, int $pct, string $link): string
{
    $tpl = (array) ($campaign['email_confirmation']['body'] ?? []);
    $raw = trim((string) ($tpl[$lang] ?? $tpl['es'] ?? ''));
    return str_replace(['{score}', '{total}', '{pct}', '{link}'], [$score, $total, $pct, $link], $raw);
}

function smtp_send(
    string $host,
    int    $port,
    string $user,
    string $pass,
    string $from,
    string $fromName,
    string $to,
    string $subject,
    string $body
): bool {
    try {
        $sock = fsockopen(($port === 465 ? 'ssl://' : '') . $host, $port, $errno, $errstr, 10);
        if (!$sock) {
            error_log("SMTP fsockopen failed: {$errstr} ({$errno})");
            return false;
        }

        $read = static function() use ($sock): string {
            return fgets($sock, 512) ?: '';
        };
        $write = static function(string $cmd) use ($sock): void {
            fwrite($sock, $cmd . "\r\n");
        };

        $read(); // 220 greeting

        $write('EHLO ' . gethostname());
        $resp = '';
        while (true) {
            $line = $read();
            $resp .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }

        // STARTTLS if port is not 465
        if ($port !== 465 && str_contains($resp, 'STARTTLS')) {
            $write('STARTTLS');
            $read();
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write('EHLO ' . gethostname());
            while (true) {
                $line = $read();
                if (strlen($line) >= 4 && $line[3] === ' ') break;
            }
        }

        // AUTH LOGIN
        $write('AUTH LOGIN');
        $read();
        $write(base64_encode($user));
        $read();
        $write(base64_encode($pass));
        $authResp = $read();
        if (!str_starts_with($authResp, '235')) {
            error_log("SMTP AUTH failed: {$authResp}");
            fclose($sock);
            return false;
        }

        $write("MAIL FROM:<{$from}>");
        $read();
        $write("RCPT TO:<{$to}>");
        $read();
        $write('DATA');
        $read();

        $date    = date('r');
        $msgId   = '<' . uniqid('fn', true) . '@' . gethostname() . '>';
        $encFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encSubj = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $message  = "Date: {$date}\r\n";
        $message .= "From: {$encFrom} <{$from}>\r\n";
        $message .= "To: <{$to}>\r\n";
        $message .= "Subject: {$encSubj}\r\n";
        $message .= "Message-ID: {$msgId}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "\r\n";
        $message .= chunk_split(base64_encode($body));
        $message .= "\r\n.";

        $write($message);
        $dataResp = $read();

        $write('QUIT');
        fclose($sock);

        return str_starts_with($dataResp, '250');

    } catch (Throwable $e) {
        error_log("SMTP exception: " . $e->getMessage());
        return false;
    }
}
