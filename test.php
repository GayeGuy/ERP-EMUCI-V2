<?php
echo "ENV MYSQL_URL: " . (getenv('MYSQL_URL') ?: 'NOT SET via getenv') . "<br>";
echo "_ENV MYSQL_URL: " . ($_ENV['MYSQL_URL'] ?? 'NOT SET via _ENV') . "<br>";
echo "_SERVER MYSQL_URL: " . ($_SERVER['MYSQL_URL'] ?? 'NOT SET via _SERVER') . "<br>";
echo "<hr>";
echo "ALL ENV:<br>";
foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'MYSQL') !== false || strpos($k, 'DB') !== false) {
        echo "$k = $v<br>";
    }
}
