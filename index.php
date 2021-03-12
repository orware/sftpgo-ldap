<?php
define("_SFTPGO", 1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/configuration.php';
require __DIR__ . '/functions.php';

isAllowedIP();
authenticateUser();