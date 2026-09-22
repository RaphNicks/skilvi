<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\Schema::install();
App\Core\Seed::purgeDemo();

echo "demo marketplace data removed\n";
echo "kept logins (password: password1)\n";
echo "  Chinedu Okafor  +234 803 111 2233  (client,worker)\n";
echo "  Ifeanyi Okoro   +234 801 000 1028  (client,worker)\n";
echo "  Adaeze Nwosu    +234 802 000 0008  (worker)\n";
echo "  Skilvi Admin    +234 800 000 0001  (admin)\n";
echo "Dashboards, search, and jobs now show only live records.\n";
