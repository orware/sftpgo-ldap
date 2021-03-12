<?php
defined('_SFTPGO') or die;

function isAllowedIP() {
    global $allowed_ips;

    if (_SFTPGO_CLI) {
        return true;
    }

    $remoteIP = $_SERVER['REMOTE_ADDR'];

    if (array_search($remoteIP, $allowed_ips) !== false) {
        return true;
    }

    denyRequest();
}

function authenticateUser() {
    $data = getData();

    if (!empty($data)) {

        try {
            global $connections;

            foreach($connections as $connectionName => $connection) {

                $connection->connect();

                $configuration = $connection->getConfiguration();
                $baseDn = $configuration->get('base_dn');

                $organizationalUnit = $baseDn;

                $user = $connection->query()
                    ->in($organizationalUnit)
                    ->where('samaccountname', '=', $data['username'])
                    ->first();

                if ($user) {
                    // Our user is a member of one of the allowed groups.
                    // Continue with authentication.
                    $userDistinguishedName = $user['distinguishedname'][0];
                    if ($connection->auth()->attempt($userDistinguishedName, $data['password'])) {
                        // User has been successfully authenticated.
                        $output = createResponseObject($connectionName, $data['username']);
                        createResponse($output);
                    } else {
                        // Username or password is incorrect.
                        denyRequest();
                    }
                }
            }

        } catch (\LdapRecord\Auth\BindException $e) {
            $error = $e->getDetailedError();

            //echo $error->getErrorCode();
            //echo $error->getErrorMessage();
            //echo $error->getDiagnosticMessage();
        }
    }

    denyRequest();
}

function createResponseObject($connectionName, $username) {
    global $home_directories, $virtual_folders, $default_output_object;

    $userHomeDirectory = str_replace('#USERNAME#', $username, $home_directories[$connectionName]);

    $output = $default_output_object;
    $output['username'] = $username;
    $output['home_dir'] = $userHomeDirectory;

    if (isset($virtual_folders[$connectionName])) {
        $output['virtual_folders'] = $virtual_folders[$connectionName];

        foreach ($output['virtual_folders'] as &$virtual_folder) {
            $virtual_folder['name'] = str_replace('#USERNAME#', $username, $virtual_folder['name']);
            $virtual_folder['mapped_path'] = str_replace('#USERNAME#', $username, $virtual_folder['mapped_path']);
        }
    }

    return $output;
}

function getData() {
    if (defined('_SFTPGO_DEBUG') && _SFTPGO_DEBUG === true) {
        global $debug_object;
        $data = $debug_object;
    }

    if (!isset($data)) {
        $data = [];
        if (_SFTPGO_CLI) {
            if (isset($_ENV['SFTPGO_AUTHD_USERNAME']) && isset($_ENV['SFTPGO_AUTHD_PASSWORD'])) {
                $username = $_ENV['SFTPGO_AUTHD_USERNAME'];
                $password = $_ENV['SFTPGO_AUTHD_PASSWORD'];
                $ip = $_ENV['SFTPGO_AUTHD_IP'];
                $protocol = $_ENV['SFTPGO_AUTHD_PROTOCOL'];
                $public_key = $_ENV['SFTPGO_AUTHD_PUBLIC_KEY'];
                $keyboard_interactive = $_ENV['SFTPGO_AUTHD_KEYBOARD_INTERACTIVE'];
                $tls_cert = $_ENV['SFTPGO_AUTHD_TLS_CERT'];

                $data = [
                    'username' => $username,
                    'password' => $password,
                    'ip' => $ip,
                    'protocol' => $protocol,
                    'public_key' => $public_key,
                    'keyboard_interactive' => $keyboard_interactive,
                    'tls_cert' => $tls_cert,
                ];
            }
        } else {
            $data = file_get_contents('php://input');
        }
    }

    if (is_string($data)) {
        $data = json_decode($data, true);
    }

    return $data;
}

function createResponse($output) {
    if (_SFTPGO_CLI) {
        echo json_encode($output);
    } else {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($output);
    }

    exit;
}

function denyRequest() {
    if (_SFTPGO_CLI) {
        $output = [
            'username' => ''
        ];
        echo json_encode($output);
    } else {
        http_response_code(500);
    }

    exit;
}

function canConnectToDirectories() {
    try {
        global $connections;

        foreach($connections as $connectionName => $connection) {

            $connection->connect();

            echo "Can connect to: " . $connectionName . '<br />';
        }

    } catch (\LdapRecord\Auth\BindException $e) {
        $error = $e->getDetailedError();

        echo "Can't connect to: " . $connectionName . '<br />';
        echo $error->getErrorCode() . '<br />';
        echo $error->getErrorMessage() . '<br />';
        echo $error->getDiagnosticMessage() . '<br />';
    }
}

function homeDirectoryEntriesExist() {

    global $connections, $home_directories;

    foreach($connections as $connectionName => $connection) {

        if (isset($home_directories[$connectionName])) {
            echo "Home Directory Entry Exists for: " . $connectionName . '<br />';
        } else {
            echo "Missing Home Directory Entry for: " . $connectionName . '<br />';
        }
    }
}