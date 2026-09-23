<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$cfg = App\Core\Config::get('mail');
$host = (string) ($cfg['host'] ?? '');
$user = (string) ($cfg['user'] ?? '');
$pass = (string) ($cfg['pass'] ?? '');
$from = (string) ($cfg['from_address'] ?? '');
$envFile = dirname(__DIR__) . '/.env';

echo "env file: " . (is_file($envFile) ? $envFile : 'NOT FOUND — put it in backend/.env') . "\n";
echo "driver:   " . ($cfg['driver'] ?? '') . "\n";
echo "host:     " . ($host !== '' ? $host : '(empty)') . "\n";
echo "port:     " . ($cfg['port'] ?? '') . "\n";
echo "encrypt:  " . (($cfg['encryption'] ?? '') !== '' ? $cfg['encryption'] : '(empty)') . "\n";
echo "user:     " . ($user !== '' ? $user : '(empty)') . "\n";
echo "password: " . ($pass !== '' ? strlen($pass) . ' chars' : '(empty)') . "\n";
echo "from:     " . ($from !== '' ? $from : '(empty)') . "\n";

if ($host === '') {
    echo "MAIL_HOST is empty so nothing is sent. Fill backend/.env and restart PHP.\n";
    exit(1);
}

$to = $from !== '' && str_contains($from, '@') ? $from : $user;
if ($to === '' || !str_contains($to, '@')) {
    echo "Set MAIL_FROM_ADDRESS to an inbox you can open, then re-run.\n";
    exit(1);
}

echo "sending a test to $to …\n";
App\Services\MailGateway::send($to, 'Skilvi mail check', 'If you got this, SMTP works.', '000000');
echo "done. last lines of storage/logs/mail.log:\n";
$log = dirname(__DIR__) . '/storage/logs/mail.log';
if (is_file($log)) {
    $lines = file($log) ?: [];
    echo implode('', array_slice($lines, -20));
}
