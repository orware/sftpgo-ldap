<?php
defined('_SFTPGO') or die;

function isAllowedIP() {
    global $allowed_ips;

    $remoteIP = $_SERVER['REMOTE_ADDR'];

    if (array_search($remoteIP, $allowed_ips) !== false) {
        return true;
    }

    denyRequest();
}

function authenticateUser() {
    $data = getPostData();

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
    global $home_directories, $default_output_object;

    $userHomeDirectory = $home_directories[$connectionName] . "\\" . $username;

    $output = $default_output_object;
    $output['username'] = $username;
    $output['home_dir'] = $userHomeDirectory;

    return $output;
}

function getPostData() {

    if (defined('_SFTPGO_DEBUG') && _SFTPGO_DEBUG === true) {
        global $debug_object;
        $post = $debug_object;
    } else {
        $post = file_get_contents('php://input');
    }

    $data = json_decode($post, true);

    return $data;
}

function createResponse($output) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($output);
    exit;
}

function denyRequest() {
    http_response_code(500);
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