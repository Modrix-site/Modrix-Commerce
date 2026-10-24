<?php

/**
 * Sends catalog mail through the SMTP settings saved by the catalog admin.
 */
final class Mailer
{
    public function __construct(private readonly Store $store) {}

    public function configured(): bool
    {
        return $this->store->setting('smtp_host') !== '' && $this->store->setting('mail_from') !== '';
    }

    public function send(string $to, string $subject, string $body): void
    {
        $host = $this->store->setting('smtp_host');
        $port = (int) $this->store->setting('smtp_port', '587');
        $encryption = $this->store->setting('smtp_encryption', 'tls');
        $username = $this->store->setting('smtp_username');
        $password = $this->store->setting('smtp_password');
        $from = $this->store->setting('mail_from');
        $fromName = $this->store->setting('mail_from_name', $this->store->catalogName());

        if ($host === '' || $from === '') {
            throw new RuntimeException('SMTP is not configured.');
        }
        if (! filter_var($from, FILTER_VALIDATE_EMAIL) || ! filter_var($to, FILTER_VALIDATE_EMAIL) || strpbrk($host.$username, "\r\n") !== false) {
            throw new RuntimeException('Check the SMTP host, username, and email addresses.');
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : '').$host.':'.$port;
        $socket = stream_socket_client($remote, $errno, $error, 15, STREAM_CLIENT_CONNECT);
        if ($socket === false) {
            throw new RuntimeException('Could not connect to the mail server.');
        }

        stream_set_timeout($socket, 15);
        $this->expect($socket, [220]);
        $this->command($socket, 'EHLO catalog.local', [250]);

        if ($encryption === 'tls') {
            $this->command($socket, 'STARTTLS', [220]);
            if (! stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('The mail server did not start encryption.');
            }
            $this->command($socket, 'EHLO catalog.local', [250]);
        }

        if ($username !== '') {
            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($username), [334]);
            $this->command($socket, base64_encode($password), [235]);
        }

        $this->command($socket, 'MAIL FROM:<'.$from.'>', [250]);
        $this->command($socket, 'RCPT TO:<'.$to.'>', [250, 251]);
        $this->command($socket, 'DATA', [354]);

        $message = 'From: '.$this->address($fromName, $from)."\r\n";
        $message .= 'To: <'.$to.">\r\n";
        $message .= 'Subject: =?UTF-8?B?'.base64_encode(str_replace(["\r", "\n"], '', $subject))."?=\r\n";
        $message .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n";
        $message .= str_replace("\n.", "\n..", str_replace(["\r\n", "\r"], "\n", $body));
        $message = str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $message));
        fwrite($socket, $message."\r\n.\r\n");
        $this->expect($socket, [250]);
        $this->command($socket, 'QUIT', [221]);
        fclose($socket);
    }

    private function address(string $name, string $email): string
    {
        $name = trim(str_replace(["\r", "\n", '"'], '', $name));

        return $name === '' ? '<'.$email.'>' : '"'.$name.'" <'.$email.'>';
    }

    /**
     * @param  list<int>  $ok
     */
    private function command($socket, string $command, array $ok): void
    {
        fwrite($socket, $command."\r\n");
        $this->expect($socket, $ok);
    }

    /**
     * @param  list<int>  $ok
     */
    private function expect($socket, array $ok): void
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (! in_array($code, $ok, true)) {
            throw new RuntimeException(trim($response) !== '' ? trim($response) : 'The mail server closed the connection.');
        }
    }
}
