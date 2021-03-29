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
            global $connections, $domains_to_strip_automatically, $convert_username_to_lowercase, $username_minimum_length, $username_blacklist;

            // Convert username to lowercase if setting is enabled:
            if (isset($convert_username_to_lowercase) && $convert_username_to_lowercase === true) {
                $beforeUsername = $data['username'];
                $data['username'] = strtolower($data['username']);

                if ($beforeUsername !== $data['username']) {
                    logMessage('Converted ' . $beforeUsername . ' to ' . $data['username']);
                }
            }

            // Strip specific organization email domains if provided:
            if (isset($domains_to_strip_automatically)) {
                logMessage('Username before domain stripping: ' . $data['username']);
                foreach($domains_to_strip_automatically as $domain) {
                    $domain = '@'.str_replace('@', '', $domain);
                    logMessage('Attempting to strip ' . $domain . ' from provided username.');
                    $data['username'] = str_replace($domain, '', $data['username']);
                }
            }

            // Prevent short usernames from being processed:
            if (isset($username_minimum_length) && $username_minimum_length > 0) {
                if (strlen($data['username']) < $username_minimum_length) {
                    logMessage('Denying ' . $data['username'] . ' since length is less than minimum allowed (' . $username_minimum_length . ')');
                    denyRequest();
                }
            }

            // Prevent blacklisted usernames from being processed:
            if (isset($username_blacklist) && !empty($username_blacklist)) {
                if (array_search($data['username'], $username_blacklist) !== false) {
                    logMessage('Denying ' . $data['username'] . ' since it is in the username blacklist');
                    denyRequest();
                }
            }

            foreach($connections as $connectionName => $connection) {

                logMessage('Before connection attempt to ' . $connectionName);
                $connection->reconnect();
                logMessage('After connection attempt to ' . $connectionName);

                $configuration = $connection->getConfiguration();
                $baseDn = $configuration->get('base_dn');

                $organizationalUnit = $baseDn;

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
                        $groups = getUserGroups($user);

                        // User has been successfully authenticated.
                        logMessage('After authentication attempt for: ' . $data['username'] . ' (success!)');
                        $output = createResponseObject($connectionName, $data['username'], $groups);

                        logMessage('Disconnecting from ' . $connectionName);
                        $connection->disconnect();
                        createResponse($output);
                    } else {
                        // Username or password is incorrect.
                        logMessage('After authentication attempt for: ' . $data['username'] . ' (failed!)');

                        logMessage('Disconnecting from ' . $connectionName);
                        $connection->disconnect();
                        denyRequest();
                    }
                } else {
                   logMessage('Disconnecting from ' . $connectionName);
                   $connection->disconnect();
                }

                logMessage('User lookup failed for: ' . $data['username']);
            }

        } catch (\LdapRecord\Auth\BindException $e) {
            $error = $e->getDetailedError();

            logMessage($error->getErrorMessage());
            //echo $error->getErrorCode();
            //echo $error->getErrorMessage();
            //echo $error->getDiagnosticMessage();
        }
    }

    denyRequest();
}

function createResponseObject($connectionName, $username, $groups = []) {
    global $home_directories,
           $virtual_folders,
           $default_output_object,
           $connection_output_objects,
           $user_output_objects,
           $allowed_groups,
           $auto_groups_mode,
           $auto_groups_mode_virtual_folder_template,
           $allowed_group_prefixes;

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

    // Allow for username-specific folders in Remote SFTP Proxy:
    if (isset($output['filesystem']['sftpconfig'])) {
        logMessage('Reviewing Remote SFTP Config for user to see if username replacement is needed');

        // Home Directory doesn't seem needed when using Remote SFTP Proxy option:
        $output['home_dir'] = '';

        $output['filesystem']['sftpconfig']['endpoint'] = str_replace('#USERNAME#', $username, $output['filesystem']['sftpconfig']['endpoint']);
        $output['filesystem']['sftpconfig']['username'] = str_replace('#USERNAME#', $username, $output['filesystem']['sftpconfig']['username']);

        if ($output['filesystem']['sftpconfig']['password']['status'] === 'Plain') {
            if (strpos($output['filesystem']['sftpconfig']['password']['payload'], '#PASSWORD#') === 0) {
                logMessage('Retrieving User Password from Request for Dynamic Replacement');
                $data = getData();
                $password = $data['password'];
                unset($data);
                logMessage('Retrieved User Password from Request for Dynamic Replacement');

                $output['filesystem']['sftpconfig']['password']['payload'] = str_replace('#PASSWORD#', $password, $output['filesystem']['sftpconfig']['password']['payload']);
            }
        }

        $output['filesystem']['sftpconfig']['password']['additional_data'] = str_replace('#USERNAME#', $username, $output['filesystem']['sftpconfig']['password']['additional_data']);
        $output['filesystem']['sftpconfig']['prefix'] = str_replace('#USERNAME#', $username, $output['filesystem']['sftpconfig']['prefix']);
    }

    if (isset($virtual_folders[$connectionName])) {
        $output['virtual_folders'] = $virtual_folders[$connectionName];

        foreach ($output['virtual_folders'] as &$virtual_folder) {
            $virtual_folder['name'] = str_replace('#USERNAME#', $username, $virtual_folder['name']);
            $virtual_folder['mapped_path'] = str_replace('#USERNAME#', $username, $virtual_folder['mapped_path']);
        }
    }

    // Support for automatically creating virtual folders for allowed groups the user may be a member of:
    if (!empty($groups)) {
        if ($auto_groups_mode) {
            foreach($groups as $group) {

                $allowed = true;

                if (!empty($allowed_group_prefixes)) {
                    $allowed = false;
                    foreach($allowed_group_prefixes as $allowed_group_prefix) {
                        if (strpos($group, $allowed_group_prefix) === 0) {
                            $allowed = true;
                            $group = str_replace($allowed_group_prefix, '', $group);
                        }
                    }
                }

                if ($allowed) {
                    if (isset($auto_groups_mode_virtual_folder_template)) {
                        foreach($auto_groups_mode_virtual_folder_template as $virtual_group_folder) {

                            $virtual_group_folder['name'] = str_replace('#GROUP#', $group, $virtual_group_folder['name']);
                            $virtual_group_folder['mapped_path'] = str_replace('#GROUP#', $group, $virtual_group_folder['mapped_path']);
                            $virtual_group_folder['virtual_path'] = str_replace('#GROUP#', $group, $virtual_group_folder['virtual_path']);

                            $output['virtual_folders'][] = $virtual_group_folder;

                            // Defaulting to open permissions on the virtual group folder:
                            $output['permissions'][$virtual_group_folder['virtual_path']] = ["*"];
                        }
                    }
                }
            }
        } else {
            if (!empty($allowed_groups)) {
                foreach($groups as $group) {
                    if (isset($allowed_groups[$group])) {
                        foreach($allowed_groups[$group] as $virtual_group_folder) {

                            $virtual_group_folder['name'] = str_replace('#GROUP#', $group, $virtual_group_folder['name']);
                            $virtual_group_folder['mapped_path'] = str_replace('#GROUP#', $group, $virtual_group_folder['mapped_path']);
                            $virtual_group_folder['virtual_path'] = str_replace('#GROUP#', $group, $virtual_group_folder['virtual_path']);

                            $output['virtual_folders'][] = $virtual_group_folder;

                            // Defaulting to open permissions on the virtual group folder:
                            $output['permissions'][$virtual_group_folder['virtual_path']] = ["*"];
                        }
                    }
                }
            }
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

function getUserGroups($user) {
    $groups = array();

    if (isset($user['memberof'])) {
        if (isset($user['memberof']['count'])) {
            unset($user['memberof']['count']);
        }

        foreach($user['memberof'] as $group) {
            $group = str_replace('CN=', '', $group);

            $endGroupName = strpos($group, ',OU');

            $group = substr($group, 0, $endGroupName);

            // Perform uniformity transformations:
            $group = strtolower($group);
            $group = str_replace('#', '', $group);
            $group = str_replace('(', '', $group);
            $group = str_replace(')', '', $group);
            $group = str_replace(' ', '-', $group);
            $group = str_replace('&', 'and', $group);
            $group = preg_replace('/[^a-zA-Z0-9\-\._]/','', $group);

            if (!empty($group)) {
                $groups[] = $group;
            }
        }
    }

    return $groups;
}

function logMessage($message, $extra = []) {
    if (defined('_SFTPGO_LOG') && _SFTPGO_LOG === true) {
        global $log;

        $log->info($message, $extra);
    }
}