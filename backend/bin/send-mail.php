<?php

declare(strict_types=1);

/*
 * Sends emails waiting in the outbox (decision D-016): new-enquiry alerts and
 * password reset links that could not be sent straight away are retried here.
 * It also queues the weekly "content due for review" email to administrators
 * (D-025), at most once in 7 days and only when something is due.
 *
 *   php bin/send-mail.php
 *
 * Schedule every 5 minutes with cPanel → Cron Jobs (docs/RUNBOOKS.md, RB-17).
 * Does nothing while email sending is not configured (MAIL_TRANSPORT).
 */

use Paxofi\Core\Configuration\EnvLoader;
use Paxofi\Core\Configuration\Environment;
use Paxofi\Core\Logging\StreamLogger;
use Paxofi\CorporateWebsite\Bootstrap\ApiApplication;
use Paxofi\CorporateWebsite\Bootstrap\Settings;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

$environment = Environment::from((new EnvLoader())->load(dirname(__DIR__) . '/.env'));
$settings = Settings::fromEnvironment($environment);
if ($settings->mailTransport === null) {
    fwrite(STDOUT, "Email sending is not configured (MAIL_TRANSPORT); nothing to do.\n");
    exit(0);
}
$application = new ApiApplication($settings, new StreamLogger(fopen('php://stderr', 'wb')));

try {
    $due = $application->contentReviewReminder()->run();
    if ($due > 0) {
        fwrite(STDOUT, sprintf("%s content review reminder queued: %d item%s due.\n", gmdate('Y-m-d H:i:s'), $due, $due === 1 ? '' : 's'));
    }
} catch (Throwable $exception) {
    // A reminder problem must never stop other emails going out.
    fwrite(STDERR, 'Content review reminder failed: ' . $exception->getMessage() . PHP_EOL);
}

try {
    $result = $application->outboxSender()->run(50, 240.0);
    if ($result['sent'] + $result['failed'] > 0) {
        fwrite(STDOUT, sprintf("%s mail: %d sent, %d failed (will retry).\n", gmdate('Y-m-d H:i:s'), $result['sent'], $result['failed']));
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Sending mail failed: ' . $exception->getMessage() . PHP_EOL);
    $application->errorAlerts()->report('Emails could not be sent', ['kind' => 'send-mail', 'error' => $exception->getMessage()]);
    $application->errorAlerts()->flush();
    exit(1);
}
$application->errorAlerts()->flush();
