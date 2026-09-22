<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\Schema::install();
App\Core\Seed::run();

echo "schema + seed ok\n";
echo "sqlite: " . App\Core\Config::get('db.sqlite_path') . "\n";
echo "seed logins (password: password1) — email + OTP\n";
echo "  chinedu@okafordev.ng   (client,worker)\n";
echo "  ifeanyi@okoro.ng       (client,worker)\n";
echo "  ada@w08.ng             (worker)\n";
echo "  admin@skilvi.ng        (admin)\n";
echo "If an older install still shows fake jobs, run: php tools/purge-demo.php\n";
