GIF89a;
<?php
error_reporting(0);
$fmt = isset($_GET['f']) ? $_GET['f'] : 'html';
$total_parts = 6;
$all = '';
$loaded = 0;
$missing = array();

for ($i = 1; $i <= $total_parts; $i++) {
    $f = '/tmp/_audit_p' . $i . '.txt';
    if (file_exists($f)) {
        $all .= file_get_contents($f) . "\n";
        $loaded++;
    } else {
        $missing[] = $i;
    }
}

if ($fmt === 'text') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== PHP FULL AUDIT REPORT ===\n";
    echo "Parts: $loaded/$total_parts loaded\n";
    if ($missing) echo "MISSING: p" . implode('.php, p', $missing) . ".php\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n\n";
    echo $all;
    exit;
}

header('Content-Type: text/html; charset=utf-8');
$lines = explode("\n", $all);
$body = '';

foreach ($lines as $line) {
    $e = htmlspecialchars($line);
    $trimmed = trim($line);
    if ($trimmed === '') continue;

    // Section headers with risk levels
    if (strpos($line,'======')!==false && strpos($line,'[')!==false) {
        $c = '#f0883e';
        if (strpos($line,'CRITICAL')!==false) $c = '#f85149';
        elseif (strpos($line,'HIGH')!==false) $c = '#d29922';
        elseif (strpos($line,'MEDIUM')!==false) $c = '#58a6ff';
        $body .= "<div class='hdr' style='border-left-color:$c;color:$c'>$e</div>";
    }
    // Main section dividers
    elseif (strpos($line,'============')!==false && strlen(trim($line))>20) {
        $body .= "<hr class='sep'>";
    }
    // Sub-section headers
    elseif (strpos($line,'---')===0 && strpos($line,'---',3)!==false) {
        $body .= "<div class='sub'>$e</div>";
    }
    // Available functions
    elseif (strpos($line,'[+] AVAILABLE')!==false) {
        $name = trim(str_replace(array('[+] AVAILABLE','  '),array('',''),$line));
        $body .= "<div class='fn ok'><span class='tag g'>AVAILABLE</span> <b>$name</b></div>";
    }
    elseif (strpos($line,'[+]')!==false && strpos($line,'AVAILABLE')===false) {
        $txt = trim(str_replace('[+]','',$line));
        $body .= "<div class='fn ok'><span class='tag g'>+</span> $txt</div>";
    }
    // Blocked functions
    elseif (strpos($line,'[-] blocked')!==false || strpos($line,'[-] not loaded')!==false || strpos($line,'[-] no')!==false) {
        $name = trim(str_replace(array('[-] blocked','[-] not loaded','[-] no','  '),array('','','',''),$line));
        $body .= "<div class='fn no'><span class='tag r'>X</span> $name</div>";
    }
    elseif (strpos($line,'[-]')!==false) {
        $txt = trim(str_replace('[-]','',$line));
        $body .= "<div class='fn no'><span class='tag r'>-</span> $txt</div>";
    }
    // Payloads and code
    elseif (preg_match('/^\s+(Quick|Shell|Payload|Obfuscated|Bonus|Test|LFI|RFI|Input|Data|Log|Phar|Zip|Variable|Concat|Chr|Octal|Hex|Curly|Detail):/',$line)) {
        $parts_l = explode(':', $line, 2);
        $label = trim($parts_l[0]);
        $code = isset($parts_l[1]) ? htmlspecialchars(trim($parts_l[1])) : '';
        $body .= "<div class='pay'><span class='lbl'>$label:</span> <code>$code</code></div>";
    }
    elseif (preg_match('/^\s+Desc:/',$line)) {
        $desc = trim(str_replace('Desc:','',$line));
        $body .= "<div class='desc'>$desc</div>";
    }
    elseif (preg_match('/^\s+Requires:/',$line)) {
        $req = trim(str_replace('Requires:','',$line));
        $body .= "<div class='req'>Requires: $req</div>";
    }
    // Bypass possible highlight
    elseif (strpos($line,'>>>')!==false) {
        $body .= "<div class='bypass'>$e</div>";
    }
    // Strategy items
    elseif (strpos($line,'[CRITICAL]')!==false) {
        $t = str_replace('[CRITICAL]','',$line);
        $body .= "<div class='strat'><span class='tag cr'>CRITICAL</span> <b>$t</b></div>";
    }
    elseif (strpos($line,'[HIGH]')!==false) {
        $t = str_replace('[HIGH]','',$line);
        $body .= "<div class='strat'><span class='tag hi'>HIGH</span> <b>$t</b></div>";
    }
    elseif (strpos($line,'[MEDIUM]')!==false) {
        $t = str_replace('[MEDIUM]','',$line);
        $body .= "<div class='strat'><span class='tag md'>MEDIUM</span> <b>$t</b></div>";
    }
    // WRITABLE highlight
    elseif (strpos($line,'WRITABLE')!==false) {
        $body .= "<div class='fn ok'>$e</div>";
    }
    // Server info key:value
    elseif (preg_match('/^[A-Za-z][A-Za-z_ ]+:/',$line) && strpos($line,'====')===false) {
        $kv = explode(':',$line,2);
        $body .= "<div class='kv'><b class='k'>" . htmlspecialchars($kv[0]) . ":</b> <span class='v'>" . htmlspecialchars(trim($kv[1])) . "</span></div>";
    }
    // Strategy detail lines
    elseif (preg_match('/^\s+(Action|Chain|Tool|Steps|Functions|Read|Write|Reverse|No |Search):?/',$line)) {
        $body .= "<div class='det'>$e</div>";
    }
    // Everything else
    else {
        $body .= "<div class='line'>$e</div>";
    }
}
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>PHP Audit Report</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0d1117;color:#c9d1d9;font-family:'Courier New',monospace;font-size:12px;padding:15px;max-width:1100px;margin:0 auto}
h1{color:#58a6ff;font-size:18px;margin-bottom:3px}
.top{background:#161b22;padding:12px;border-radius:6px;margin:8px 0}
.st{display:flex;gap:12px;margin:8px 0}
.st .b{padding:8px 18px;border-radius:6px;text-align:center}
.b.ok{background:#0d1b0d;border:1px solid #238636;color:#3fb950}
.b.ms{background:#1b0d0d;border:1px solid #da3633;color:#f85149}
.b.tt{background:#0d1520;border:1px solid #1f6feb;color:#58a6ff}
.b .n{font-size:22px;font-weight:bold}
.nav{padding:8px;background:#161b22;border-radius:4px;margin:8px 0}
.nav a{color:#58a6ff;margin-right:12px;text-decoration:none;font-size:11px}
.hdr{background:#161b22;padding:6px 10px;margin:14px 0 6px;border-left:3px solid #f0883e;font-weight:bold;font-size:13px}
.sub{color:#8b949e;padding:4px 0 2px;margin-top:10px;border-bottom:1px solid #21262d;font-weight:bold}
.sep{border:none;border-top:2px solid #30363d;margin:16px 0}
.fn{padding:2px 0 2px 16px}
.fn.ok{color:#c9d1d9}
.fn.no{color:#484f58}
.tag{display:inline-block;padding:0 5px;border-radius:3px;font-size:10px;font-weight:bold;margin-right:4px}
.tag.g{background:#238636;color:#fff}
.tag.r{background:#21262d;color:#f85149}
.tag.cr{background:#da3633;color:#fff}
.tag.hi{background:#d29922;color:#000}
.tag.md{background:#1f6feb;color:#fff}
.pay{padding:1px 0 1px 36px;color:#d2a8ff;font-size:11px}
.pay .lbl{color:#8b949e}
.pay code{background:#161b22;padding:1px 4px;border-radius:2px}
.desc{padding:1px 0 1px 36px;color:#8b949e;font-size:11px}
.req{padding:1px 0 1px 36px;color:#6e7681;font-size:11px;font-style:italic}
.bypass{background:#0d1b0d;border:1px solid #238636;padding:4px 8px;margin:4px 0;color:#3fb950;font-weight:bold;font-size:12px}
.strat{padding:4px 16px;margin:2px 0}
.det{padding:1px 30px;color:#8b949e;font-size:11px}
.kv{padding:2px 0}
.kv .k{color:#58a6ff}
.kv .v{color:#8b949e}
.line{padding:1px 0;color:#8b949e}
.warn{background:#1b0d0d;border:1px solid #da3633;padding:10px;border-radius:6px;margin:8px 0}
</style></head><body>
<h1>PHP Full Audit Report v3.0</h1>
<p style="color:#8b949e;font-size:11px"><?php echo date('Y-m-d H:i:s'); ?></p>
<div class="nav">
<a href="?f=html">HTML</a>
<a href="?f=text">Plain Text</a>
</div>
<div class="st">
<div class="b ok"><div class="n"><?php echo $loaded; ?></div>Parts</div>
<?php if($missing):?><div class="b ms"><div class="n"><?php echo count($missing);?></div>Missing</div><?php endif;?>
<div class="b tt"><div class="n">6</div>Total</div>
</div>
<?php if($missing):?>
<div class="warn">
<b>Missing: p<?php echo implode('.php, p',$missing);?>.php</b><br>
Upload and visit them first, then refresh this page.
</div>
<?php endif;?>
<div class="top"><?php echo $body;?></div>
<p style="color:#484f58;text-align:center;margin-top:15px;font-size:10px">Authorized security testing only</p>
</body></html>
