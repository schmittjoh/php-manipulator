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

class NormalizingNodeVisitor extends \PHPParser_NodeVisitorAbstract
{
    private $imports;

    public function enterNode(\PHPParser_Node $node)
    {
        if ($node instanceof \PHPParser_Node_Stmt_Namespace) {
            $this->imports = array();
        } else if ($node instanceof \PHPParser_Node_Stmt_Use) {
            foreach ($node->uses as $use) {
                assert($use instanceof \PHPParser_Node_Stmt_UseUse);
                $this->imports[$use->alias] = implode("\\", $use->name->parts);
            }
        }
    }

    public function leaveNode(\PHPParser_Node $node)
    {
        if (isset($node->stmts)) {
            $node->stmts = new BlockNode($node->stmts, $node->getLine());
        }

        if ($node instanceof \PHPParser_Node_Stmt_Namespace) {
            $node->setAttribute('imports', $this->imports);
        }
    }
}