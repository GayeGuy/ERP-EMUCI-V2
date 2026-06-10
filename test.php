<?php
echo "MYSQL_URL: " . (getenv('MYSQL_URL') ? 'SET' : 'NOT SET') . "<br>";
echo "HOST: " . getenv('MYSQLHOST') . "<br>";
echo "PORT: " . getenv('MYSQLPORT') . "<br>";