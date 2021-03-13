# sftpgo-ldap

Simple integration for use with SFTPGo's External Authentication capabilities.

### Quick Web Instructions:

* Once cloned, make sure to run `composer install` to add in the LdapRecord (and Monolog) dependencies.
* Copy `configuration.example.php` to `configuration.php` and then begin making adjustments (primarily, you should add `$connections`, adjust `$home_directories`, and add `$virtual_folders`, if desired, and edit the `$default_output_object` if you need to since that's used as a template for what's passed back to SFTPGo).
* You can add additional `allowed_ips` for the PHP code to respond to (I added my remote IP of the SFTPGo server and my home IP in addition to the localhost related ones).
* You can add one or more named LDAP connections, each pointing to a different LDAP server (if needed) or simply to different Organizational Units. (e.g. one for staff, one for students, and possibly others for different use cases). Each of the connections will be tried in order.
* In addition to the named connections, you will need to define a home directory for each of the named LDAP connections too. These would correspond to directories on the SFTPGo server.
* You may also define one or more virtual directories that would be displayed to users as well after they login.
* Placeholder support is present for the `#USERNAME#` key (for any home directories you define, or for the `name` and `mapped_path` keys when defining virtual directories), which you can use so that each LDAP user would automatically be assigned their own user-specific folder within the home directory defined for the LDAP connection (e.g. if `C:\test\#USERNAME#` is the home directory and my username is `example` then when I login via SFTP I would have the `C:\test\example` folder created where my files would be placed).
* There is a default output object template in the configuration that can be edited if you wanted a different set of defaults to be applied for your users (currently, the only parts that will be changed in the final object response are the `username` and `home_dir` values, and any virtual folders defined will be added as the response object is being generated, since extra processing of the `#USERNAME#` placeholders may be needed).

### Quick CLI Instructions:

* A ZIP file will be attached that already has the LdapRecord/Monolog dependencies included.
* Since the PHP code is mostly portable, I found the ExeOutput for PHP product and used that to create an EXE, but rather than embedding all of the PHP files, they are all present within the `Data` folder you see after extracting the ZIP file.
* After extracting the ZIP file (I would recommend putting it somewhere easy, like in a `C:\cli` folder or something similar) you will have: an `sftpgo-ldap-cli.exe` file, a `Data` folder (containing the bulk of the PHP code used), and an `OpenLDAP` folder (the OpenLDAP folder is not needed directly by the EXE, so can be deleted if you don't need it...it is mainly provided as convenience, allowing you to easily copy that folder into your `C:` root if you don't already have it there to help with the TLS related issues shared below).
* The rest of the configuration related comments shared above for the Web Instructions would still apply for the CLI usage, since the same code is being used in both situations.
* In the SFTPGo JSON configuration, make sure to use double backslashes when entering in the Windows paths (if you extracted the ZIP in `C:\cli` then the `external_auth_hook` path should be set to: `C:\\cli\\sftpgo-ldap-cli.exe` in the SFTPGo JSON configuration file).
* NOTE: When testing out the web vs. CLI approaches, a noticeable difference in speed was discernible (web approach seemed to take about 1 second when logging in via SFTP, whereas the CLI approach took anywhere from 4-8 seconds). At the moment though, not enough is known about the way the EXE file is constructed that includes the PHP runtime to understand whether or not anything else can be done at the moment to improve the speed of the CLI approach using this particular type of EXE (although you could also simply grab the regular PHP code as well, install PHP on the SFTPGo server, and create a bat file I suppose...that actually seems to run at the same speed here on my end as the web approach).
* Additionally, initially I wanted to create a separate set of PowerShell scripts to allow for something equivalent to the PHP web-based approached I had first put together. However, I found that there are some limitations related to how the PowerShell Active Directory Module handles its communication (it does so over the 9389 AD Web Services port, and doesn't seem like alternative ports can be used), so instead I added CLI support into the PHP code so it could be used for either purpose.

### Server Side Tips:

* You will need to have PHP with the LDAP extension installed on your server for this project to function.
* If using TLS, the tip on this page (https://ldaprecord.com/docs/core/v2/configuration/#debugging) may be helpful since the `TLS_REQCERT never` option may need to be added locally if testing on Windows (the file `C:\OpenLDAP\sysconf\ldap.conf` will likely need to be created and that config line added to it) or on your live server (Linux: `/etc/ldap/ldap.conf`) along with the "proper" way also described on the page.
* This project has a very simple `check.php` script you can access just to help ensure you can connect to the LDAP connections you've defined (make sure your IP is in the `allowed_ips` otherwise you'll just get a 500 error. At a basic level it can at least let you know if you have the LDAP extension for PHP enabled in your installation.
* To run a basic test without SFTPGo, you may adjust `_SFTPGO_DEBUG` inside of `index.php` to `true` and then adjust the `$debug_object` with the username/password of a real account and see if you successfully receive a JSON response object back, which would indicate the authentication was successful against one of your LDAP connections. If you do use this feature, make sure to turn it back off again, since it will prevent normal logins from working (since it'll always use the `$debug_object`.
* Basic logging has also been added that you can temporarily enable to get a better idea for where you may be having a problem by setting `_SFTPGO_LOG` to true (and a new file for the day should be created in the logs folder...this is useful for both the web and CLI scenarios).
* Also, depending on your server setup, make sure when you add the URL in the `external_auth_hook` option, that you point it explicitly to the `index.php` file (in my case, I pointed to the containing directory without a forward slash at the end, which triggered a 301 redirect and it seemed like SFTPGo wasn't processing logins correctly in that situation).

I hope this is helpful for others wanting to make use of SFTPGo and LDAP/Active Directory!