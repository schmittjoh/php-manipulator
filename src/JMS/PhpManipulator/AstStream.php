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

namespace JMS\PhpManipulator;

use JMS\PhpManipulator\PhpParser\InOrderTraversal;

/**
 * Small wrapper for an AST stream.
 *
 * This is usually used as part of the SimultaneousTokenAstStream, and not
 * as a standalone analysis.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AstStream
{
    public $previous;
    public $node;
    public $next;

    private $nodes;
    private $i;

    public function setAst(\PHPParser_Node $node)
    {
        $this->nodes = array();
        $nodes = &$this->nodes;

        InOrderTraversal::traverseWithCallback($node, function($node) use (&$nodes) {
            $nodes[] = $node;
        });

        $this->reset();
    }

    public function getNodes()
    {
        return $this->nodes;
    }

    public function skipUntil($classes)
    {
        if (!is_array($classes)) {
            $classes = array($classes);
        }

        $visitedNodes = array();
        while ($this->moveNext()) {
            $visitedNodes[] = $this->node;
            foreach ($classes as $class) {
                if ($this->node instanceof $class) {
                    return;
                }
            }
        }

        throw new \RuntimeException(sprintf('Could not find any node of any of types (%s). Found following classes: %s', implode(', ', $classes), implode(', ', array_map('get_class', $visitedNodes))));
    }

    public function reset()
    {
        $this->i = -1;
        $this->moveNext();
    }

    public function moveNext()
    {
        $this->previous = $this->node;
        $this->node = $this->next;

        $this->next = null;
        if (isset($this->nodes[$this->i + 1])) {
            $this->next = $this->nodes[++$this->i];
        }

        return null !== $this->node;
    }
}