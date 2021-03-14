<?php
defined('_SFTPGO') or die;

function isAllowedIP() {
    global $allowed_ips;

    logMessage('Starting Execution Process');
    if (defined('_SFTPGO_CLI') && _SFTPGO_CLI === true) {
        logMessage('CLI Execution Mode...skipping Allowed IP check');
        return true;
    }

    $remoteIP = $_SERVER['REMOTE_ADDR'];

    if (array_search($remoteIP, $allowed_ips) !== false) {
        logMessage('Web Execution Mode...' . $remoteIP . ' is allowed.');
        return true;
    }

    logMessage('Web Execution Mode...' . $remoteIP . ' is not allowed.');

    denyRequest();
}

function authenticateUser() {
    logMessage('Before getData()');
    $data = getData();
    logMessage('After getData()');

    if (!empty($data)) {

        try {
            global $connections, $domains_to_strip_automatically;

            foreach($connections as $connectionName => $connection) {

                logMessage('Before connection attempt to ' . $connectionName);
                $connection->connect();
                logMessage('After connection attempt to ' . $connectionName);

                $configuration = $connection->getConfiguration();
                $baseDn = $configuration->get('base_dn');

                $organizationalUnit = $baseDn;

                // Strip specific organization email domains if provided:
                if (isset($domains_to_strip_automatically)) {
                    foreach($domains_to_strip_automatically as $domain) {
                        $domain = '@'.str_replace('@', '', $domain);
                        logMessage('Attempting to strip ' . $domain . ' from provided username.');
                        $data['username'] = str_replace($domain, '', $data['username']);
                    }
                }

                $user = $connection->query()
                    ->in($organizationalUnit)
                    ->where('samaccountname', '=', $data['username'])
                    ->first();

                if ($user) {
                    logMessage('Username exists: ' . $data['username']);
                    // Our user is a member of one of the allowed groups.
                    // Continue with authentication.
                    $userDistinguishedName = $user['distinguishedname'][0];

                    logMessage('Before authentication attempt for: ' . $data['username']);
                    if ($connection->auth()->attempt($userDistinguishedName, $data['password'])) {
                        // User has been successfully authenticated.
                        logMessage('After authentication attempt for: ' . $data['username'] . ' (success!)');
                        $output = createResponseObject($connectionName, $data['username']);
                        createResponse($output);
                    } else {
                        // Username or password is incorrect.
                        logMessage('After authentication attempt for: ' . $data['username'] . ' (failed!)');
                        denyRequest();
                    }
                }

                logMessage('User lookup failed for: ' . $data['username']);
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
    global $home_directories, $virtual_folders, $default_output_object, $connection_output_objects, $user_output_objects;

    $userHomeDirectory = str_replace('#USERNAME#', $username, $home_directories[$connectionName]);

    $output = $default_output_object;

    // Connection-specific output objects override the default one:
    if (isset($connection_output_objects[$connectionName])) {
        logMessage('Using connection-specific output object override.');
        $output = $connection_output_objects[$connectionName];
    }

    // Username-specific output objects override the default and connection-specific ones:
    if (isset($user_output_objects[$username])) {
        logMessage('Using username-specific output object override.');
        $output = $user_output_objects[$username];
    }

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
        logMessage('Using $debug_object from configuration.php (authentication may fail if this object does not have correct credentials at the moment.)');
        $data = $debug_object;
    }

    if (!isset($data)) {
        $data = [];
        if (defined('_SFTPGO_CLI') && _SFTPGO_CLI === true) {
            $environment = getenv();

            if (defined('_SFTPGO_DEBUG_ENV') && _SFTPGO_DEBUG_ENV === true) {
                echo json_encode($environment, true);
                sleep(15);
                exit;
            }

            if (isset($environment['SFTPGO_AUTHD_USERNAME']) && isset($environment['SFTPGO_AUTHD_PASSWORD'])) {
                $username = $environment['SFTPGO_AUTHD_USERNAME'];
                $password = $environment['SFTPGO_AUTHD_PASSWORD'];
                $ip = $environment['SFTPGO_AUTHD_IP'];
                $protocol = $environment['SFTPGO_AUTHD_PROTOCOL'];
                $public_key = $environment['SFTPGO_AUTHD_PUBLIC_KEY'];
                $keyboard_interactive = $environment['SFTPGO_AUTHD_KEYBOARD_INTERACTIVE'];
                $tls_cert = $environment['SFTPGO_AUTHD_TLS_CERT'];

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
    if (defined('_SFTPGO_CLI') && _SFTPGO_CLI === true) {
        echo json_encode($output);
    } else {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($output);
    }

    logMessage('Authentication Successful');
    exit;
}

function denyRequest() {
    if (defined('_SFTPGO_CLI') && _SFTPGO_CLI === true) {
        $output = [
            'username' => ''
        ];
        echo json_encode($output);
    } else {
        http_response_code(500);
    }

    logMessage('Authentication Failed');
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

function logMessage($message, $extra = []) {
    if (defined('_SFTPGO_LOG') && _SFTPGO_LOG === true) {
        global $log;

        $log->info($message, $extra);
    }
}