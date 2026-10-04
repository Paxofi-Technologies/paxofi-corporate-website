<?php

// A one-connection SMTP server for SmtpTransportTest: php fake-smtp-server.php <port> <transcript file> [reject-rcpt]
[, $port, $transcript] = $argv;
$reject = ($argv[3] ?? '') === 'reject-rcpt';
$server = stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $error);
fwrite(STDOUT, "ready\n");
$client = stream_socket_accept($server, 10);
$log = '';
$say = static function (string $line) use ($client): void {
    fwrite($client, $line . "\r\n");
};
$say('220 fake.example ESMTP');
while (($line = fgets($client)) !== false) {
    $log .= 'C: ' . $line;
    $command = strtoupper(substr(trim($line), 0, 4));
    if ($command === 'EHLO') {
        $say('250-fake.example');
        $say('250 AUTH LOGIN PLAIN');
    } elseif (trim($line) === 'AUTH LOGIN') {
        $say('334 VXNlcm5hbWU6');
        $log .= 'C: ' . fgets($client);
        $say('334 UGFzc3dvcmQ6');
        $log .= 'C: ' . fgets($client);
        $say('235 Authentication succeeded');
    } elseif ($command === 'MAIL') {
        $say('250 OK');
    } elseif ($command === 'RCPT') {
        $say($reject ? '550 No such user here' : '250 Accepted');
    } elseif ($command === 'DATA') {
        $say('354 Enter message');
        $data = '';
        while (($dataLine = fgets($client)) !== false && $dataLine !== ".\r\n") {
            $data .= $dataLine;
        }
        $log .= "DATA:\n" . $data;
        $say('250 Queued');
    } elseif ($command === 'QUIT') {
        $say('221 Bye');
        break;
    } else {
        $say('500 What?');
    }
}
file_put_contents($transcript, $log);
