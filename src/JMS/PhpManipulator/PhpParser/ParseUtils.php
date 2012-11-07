<?php

/*
 * Copyright 2012 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\PhpManipulator\PhpParser;

abstract class ParseUtils
{
    public static function parse($code)
    {
        $lexer = new \PHPParser_Lexer($code);
        $parser = new \PHPParser_Parser();
        $ast = $parser->parse($lexer);

        $traverser = new \PHPParser_NodeTraverser();
        $traverser->addVisitor(new NormalizingNodeVisitor());
        $traverser->addVisitor(new \PHPParser_NodeVisitor_NameResolver());
        $ast = $traverser->traverse($ast);

        switch (count($ast)) {
            case 0:
                $ast = new BlockNode(array());
                break;

            case 1:
                $ast = $ast[0];
                break;

            default:
                $ast = new BlockNode($ast);
        }

        // This is currently only available when using the schmittjoh/PHP-Parser fork.
        if (class_exists('PHPParser_NodeVisitor_NodeConnector')) {
            $traverser = new \PHPParser_NodeTraverser();
            $traverser->addVisitor(new \PHPParser_NodeVisitor_NodeConnector());
            $traverser->traverse(array($ast));
        }

        return $ast;
    }

    private final function __construct() { }
}