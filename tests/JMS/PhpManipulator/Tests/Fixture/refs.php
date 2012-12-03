<?php

function foo(&$foo, &$bar) {}

function(&$foo, &$bar) use (&$baz) { };

class Foo
{
    function foo(&$foo, &$bar) { }
}

foreach ($foo as &$bar) { }
foreach ($foo as $name => &$bar) { }

$arr = array(&$rs, foo(&$bar));