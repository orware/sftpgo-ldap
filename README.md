# sftpgo-ldap

Simple integration for use with SFTPGo's External Authentication capabilities.

### Quick instructions:

* Once cloned, make sure to run `composer install` to add in the LdapRecord dependency.
* Copy `configuration.example.php` to `configuration.php` and then begin making adjustments.
* You can add additional `allowed_ips` for the PHP code to respond to (I added my remote IP of the SFTPGo server and my home IP in addition to the localhost related ones).
* You can add one or more named LDAP connections, each pointing to a different LDAP server (if needed) or simply to different Organizational Units. (e.g. one for staff, one for students, and possibly others for different use cases). Each of the connections will be tried in order.
* In addition to the named connections, you will need to define a home directory for each of the named LDAP connections too. These would correspond to directories on the SFTPGo server.
* Under the current setup, each LDAP user would automatically be assigned their own user-specific folder within the home directory defined for the LDAP connection (e.g. if `C:\test` is the home directory and my username is `example` then when I login via SFTP I would have the `C:\test\example` folder created where my files would be placed).
* There is a default output object template in the configuration that can be edited if you wanted a different set of defaults to be applied for your users (currently, the only parts that will be changed in the final object response are the `username` and `home_dir` values (which will be generated based on the example in the previous bullet point).

### Server Side Tips:

* You will need to have PHP with the LDAP extension installed on your server for this project to function.
* If using TLS, the tip on this page (https://ldaprecord.com/docs/core/v2/configuration/#debugging) may be helpful since the `TLS_REQCERT never` option may need to be added locally if testing on Windows (in `C:\OpenLDAP\sysconf\ldap.conf`) or on your live server (Linux: `/etc/ldap/ldap.conf`) along with the "proper" way also described on the page.
* This project has a very simple `check.php` script you can access just to help ensure you can connect to the LDAP connections you've defined (make sure your IP is in the `allowed_ips` otherwise you'll just get a 500 error. At a basic level it can at least let you know if you have the LDAP extension for PHP enabled in your installation.
* To run a basic test without SFTPGo, you may adjust `_SFTPGO_DEBUG` inside of `index.php` to `true` and then adjust the `$debug_object` with the username/password of a real account and see if you successfully receive a JSON response object back, which would indicate the authentication was successful against one of your LDAP connections. If you do use this feature, make sure to turn it back off again, since it will prevent normal logins from working (since it'll always use the `$debug_object`.
* Depending on your server setup, make sure when you add the URL in the `external_auth_hook` option, that you point it explicitly to the `index.php` file (in my case, I pointed to the containing directory without a forward slash at the end, which triggered a 301 redirect and it seemed like SFTPGo wasn't processing logins correctly in that situation).

I hope this is helpful for others wanting to make use of SFTPGo and LDAP/Active Directory!