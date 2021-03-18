<?php
defined('_SFTPGO') or die;
define('_SFTPGO_CLI', PHP_SAPI === 'cli');
define('_SFTPGO_LOG', false);
define('_SFTPGO_DEBUG', false);
define('_SFTPGO_DEBUG_ENV', false);

use LdapRecord\Connection;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

// create a log channel
$log = new Logger('name');
$log->pushHandler(new RotatingFileHandler('logs/sftpgo-ldap.log', 30, Logger::DEBUG));

// If the debug flag is set to true, please set the username/password directly for the LDAP user you want to test below:
$debug_object = '{"username":"test","password":"test","ip":"::1","keyboard_interactive":"","protocol":"SSH","public_key":""}';

// Localhost IPs are present, but add remote IP of SFTPGo server into the list below if needed:
$allowed_ips = [
    '::1',
    '127.0.0.1'
];

// Force usernames to lowercase (helps enforce consistency when creating folder names automatically based on username):
$convert_username_to_lowercase = true;

// Special functionality to strip email domains automatically from the username if provided:
$domains_to_strip_automatically = [
    'example.com',
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

### NOTE: You may include #USERNAME# in the path you define and it will be replaced by the user's username:

$home_directories['example'] = 'C:\test\#USERNAME#';

// Optional: create virtual folder entries for each connection you have above:
$virtual_folders = [];

### NOTE: You may include #USERNAME# in the 'name' and 'mapped_path' values you define and it will be replaced by the user's username:

// Note: that for each connection you need to provide a nested array (since you can technically define more than one virtual folder per connection):
$virtual_folders['example'] = [
    [
      //"id" => 0,
      "name" => "private-#USERNAME#",
      "mapped_path" => 'C:\example-private\#USERNAME#',
      //"used_quota_size" => 0,
      //"used_quota_files" => 0,
      //"last_quota_update" => 0,
      "virtual_path" => "/private",
      "quota_size" => -1,
      "quota_files" => -1
    ]
];

// You can make adjustments here that will be used for all of your user object responses back to SFTPGo:
$default_output_object = [
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
    'permissions' => [
        "/" => ["*"],
        //"/somedir" => array("list", "download"),
    ],
    'upload_bandwidth' => 0,
    'download_bandwidth' => 0,
    'filters' => [
        'allowed_ip' => [],
        'denied_ip' => [],
    ],
    'public_keys' => [],
];

// If you want to have a specific LDAP connection use a different output object template,
// add in an entry using the connection name as key:
$connection_output_objects = [];

$connection_output_objects['default'] = [
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
    'permissions' => [
        "/" => ["*"],
        //"/somedir" => array("list", "download"),
    ],
    'upload_bandwidth' => 0,
    'download_bandwidth' => 0,
    'filters' => [
        'allowed_ip' => [],
        'denied_ip' => [],
    ],
    'public_keys' => [],
];

// If you want to have a specific LDAP username to use a different output object template,
// add in an entry using the username as key:
$user_output_objects = [];

$user_output_objects['example_username'] = [
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
    'permissions' => [
        "/" => ["*"],
        //"/somedir" => array("list", "download"),
    ],
    'upload_bandwidth' => 0,
    'download_bandwidth' => 0,
    'filters' => [
        'allowed_ip' => [],
        'denied_ip' => [],
    ],
    'public_keys' => [],
];

// If automatic groups mode is disabled, then you have to manually add the allowed groups into $allowed_groups down below:
// If enabled, then any groups you are a memberof will automatically be added in using the template below.
$auto_groups_mode = true;

$auto_groups_mode_virtual_folder_template = [
    [
      //"id" => 0,
      "name" => "groups-#GROUP#",
      "mapped_path" => 'C:\groups\#GROUP#',
      //"used_quota_size" => 0,
      //"used_quota_files" => 0,
      //"last_quota_update" => 0,
      "virtual_path" => "/groups/#GROUP#",
      "quota_size" => -1,
      "quota_files" => -1
    ]
];

// Used only when auto groups mode is enabled and will help prevent all your groups from being
// added into SFTPGo since only groups with the prefixes defined here will be automatically added
// with prefixes automatically removed when listed as a virtual folder (e.g. a group with name
// "sftpgo-example" would simply become "example").
$allowed_group_prefixes = [
    'sftpgo-'
];

// List of groups where a virtual folder will be created and associated with any group members:
$allowed_groups = [];

// Note: that for each group you need to provide a nested array (this allows for more than one virtual folder per group to be defined):
$allowed_groups['example'] = [
    [
      //"id" => 0,
      "name" => "groups-#GROUP#",
      "mapped_path" => 'C:\groups\#GROUP#',
      //"used_quota_size" => 0,
      //"used_quota_files" => 0,
      //"last_quota_update" => 0,
      "virtual_path" => "/groups/#GROUP#",
      "quota_size" => -1,
      "quota_files" => -1
    ]
];

// Add a minimum length for usernames (set to 0 to ignore length):
$username_minimum_length = 4;

// This list of usernames will simply be ignored completed (no LDAP authentication will occur):
$username_blacklist = [
    'admin',
    'apagar',
    'auto',
    'bananapi',
    'bdadmin',
    'billing',
    'bin',
    'crm',
    'csgoserver',
    'deploy',
    'eas',
    'escaner',
    'factorio',
    'fedena',
    'fernando',
    'ftp',
    'ftp_id',
    'ftpserver',
    'ftpuser',
    'furukawa',
    'gc',
    'git',
    'gitblit',
    'gmod',
    'guest',
    'hxeadm',
    'ircd',
    'kafka',
    'kk',
    'koha',
    'kms',
    'mariadb',
    'minecraft',
    'mysql',
    'node',
    'odoo',
    'oozie',
    'openvpn',
    'operator',
    'oracle',
    'pcguest',
    'pi',
    'platform',
    'plcmspip',
    'postgres',
    'prueba',
    'prueba1',
    'rpm',
    'root',
    'rs',
    'sample',
    'secretaria',
    'shutdown',
    'sinus',
    'squadserver',
    'steam',
    'student',
    'student10',
    'support',
    'sysadmin',
    'teacher',
    'teacher1',
    'teamspeak',
    'temp',
    'test',
    'test1',
    'test001',
    'teste',
    'testftp',
    'trinity',
    'ts3',
    'ts3bot',
    'ubuntu',
    'user',
    'usuario',
    'uploader',
    'vbox',
    'vboxuser',
    'voip',
    'vyos',
    'web5',
    'webftp',
    'www',
    'www-data',
    'zabbix',
    'zte',
];