<?php

$foo = 'bar';

function($foo = 'bar') { };

class A
{
    function foo($foo = 'bar') { }
}

function foo($foo = 'bar') { }

$foo = 'bar';