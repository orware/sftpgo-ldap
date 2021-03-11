<?php
define("_SFTPGO", 1);
define("_SFTPGO_DEBUG", false);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/configuration.php';
require __DIR__ . '/functions.php';

isAllowedIP();
authenticateUser();