<?php
define("_SFTPGO", 1);

if (extension_loaded('ldap')) {
    require __DIR__ . '/vendor/autoload.php';
    require __DIR__ . '/configuration.php';
    require __DIR__ . '/functions.php';

    echo 'LDAP Extension is enabled.<br />';
    if (isAllowedIP()) {
        echo 'Remote IP: ' . $_SERVER['REMOTE_ADDR'] . ' is allowed<br />';
        canConnectToDirectories();
        homeDirectoryEntriesExist();
    }
} else {
    echo 'LDAP Extension is not enabled...it must be enabled for this to work.<br />';
}
