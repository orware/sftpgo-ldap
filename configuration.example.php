<?php
defined('_SFTPGO') or die;

use LdapRecord\Connection;

// If the debug flag is set to true, please set the username/password directly for the LDAP user you want to test below:
$debug_object = '{"username":"test","password":"test","ip":"::1","keyboard_interactive":"","protocol":"SSH","public_key":""}';

// Localhost IPs are present, but add remote IP of SFTPGo server into the list below if needed:
$allowed_ips = [
    '::1',
    '127.0.0.1'
];

// Add named connection strings for each LDAP connection you want to utilize with SFTPGo:
$connections = [];

$connections['example'] = new Connection([
    // Mandatory Configuration Options
    'hosts'            => ['192.168.1.1'],
    'base_dn'          => 'dc=local,dc=com',
    'username'         => 'cn=admin,dc=local,dc=com',
    'password'         => 'password',

    // Optional Configuration Options
    'port'             => 389,
    'use_ssl'          => false,
    'use_tls'          => false,
    'version'          => 3,
    'timeout'          => 5,
    'follow_referrals' => false,

    // Custom LDAP Options
    'options' => [
        // See: http://php.net/ldap_set_option
        //LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_HARD
    ]
]);

// Create an entry for each connection you have above:
$home_directories = [];

$home_directories['example'] = 'C:\test';

// You can make adjustments here that will be used for all of your user object responses back to SFTPGo:
$default_output_object = array(
    'status' => 1,
    'username' => '',
    'expiration_date' => 0,
    'home_dir' => '',
    // Need to comment the virtual_folders entry out:
    //'virtual_folders' => array(
        //$privateFolderName
    //),
    'uid' => 0,
    'gid' => 0,
    'max_sessions' => 0,
    'quota_size' => 0,
    'quota_files' => 100000,
    'permissions' => array(
        "/" => array("*"),
        //"/somedir" => array("list", "download"),
    ),
    'upload_bandwidth' => 0,
    'download_bandwidth' => 0,
    'filters' => array(
        'allowed_ip' => array(),
        'denied_ip' => array(),
    ),
    'public_keys' => array(),
);