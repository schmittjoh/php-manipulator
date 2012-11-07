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

class InOrderTraversal
{
    private $callback;

    public static function traverseWithCallback(\PHPParser_Node $ast, $callback)
    {
        $t = new self($callback);
        $t->traverse($ast);
    }

    public function __construct($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('$callback must be a valid callback.');
        }

        $this->callback = $callback;
    }

    public function traverse(\PHPParser_Node $node)
    {
        $this->traverseInternal($node);
    }

    private function traverseInternal(\PHPParser_Node $node, \PHPParser_Node $parent = null)
    {
        if (false === $this->traverseLeft($node)) {
            return false;
        }

        if (false === call_user_func($this->callback, $node, $parent)) {
            return false;
        }

        if (false === $this->traverseRight($node)) {
            return false;
        }
    }

    private function traverseLeft(\PHPParser_Node $node)
    {
        $left = null;
        switch (true) {
            case isset($node->left):
                $left = $node->left;
                break;

            case $node instanceof \PHPParser_Node_Expr_Instanceof:
                $left = $node->expr;
                break;

            case $node instanceof \PHPParser_Node_Expr_Assign:
                $left = $node->var;
                break;

            case $node instanceof \PHPParser_Node_Expr_PropertyFetch:
            case $node instanceof \PHPParser_Node_Expr_ArrayDimFetch:
            case $node instanceof \PHPParser_Node_Expr_MethodCall:
                $left = $node->var;
                break;

            case $node instanceof \PHPParser_Node_Expr_StaticPropertyFetch:
            case $node instanceof \PHPParser_Node_Expr_StaticCall:
                $left = $node->class;
                break;

            case $node instanceof \PHPParser_Node_Expr_PostDec:
            case $node instanceof \PHPParser_Node_Expr_PostInc:
                $left = $node->var;
                break;
        }

        if (null !== $left) {
            return $this->traverseInternal($left, $node);
        }
    }

    private function traverseRight(\PHPParser_NodeAbstract $node)
    {
        $right = null;
        switch (true) {
            case isset($node->right):
                $right = $node->right;
                break;

            case $node instanceof \PHPParser_Node_Expr_Instanceof:
                $right = $node->class;
                break;

            case $node instanceof \PHPParser_Node_Expr_Assign:
                $right = $node->expr;
                break;

            case $node instanceof \PHPParser_Node_Expr_ArrayDimFetch:
                $right = $node->dim;
                break;

            case $node instanceof \PHPParser_Node_Expr_StaticPropertyFetch:
            case $node instanceof \PHPParser_Node_Expr_PropertyFetch:
                // If the property is a string, do not traverse it.
                if (is_string($node->name)) {
                    return;
                }

                $right = $node->name;
                break;

            case $node instanceof \PHPParser_Node_Expr_StaticCall:
            case $node instanceof \PHPParser_Node_Expr_MethodCall:
                if (!is_string($node->name) && false === $this->traverseInternal($node->name, $node)) {
                    return false;
                }

                foreach ($node->args as $arg) {
                    if (false === $this->traverseInternal($arg, $node)) {
                        return false;
                    }
                }

                return; // no break here

            case $node instanceof \PHPParser_Node_Stmt_Case:
                $order = array();
                if ($node->cond) {
                    $order[] = $node->cond;
                }
                $order[] = $node->stmts;

                return $this->traverseArray($order, $node);

            case $node instanceof \PHPParser_Node_Stmt_Do:
                return $this->traverseArray(array($node->stmts, $node->cond), $node);

            case $node instanceof \PHPParser_Node_Stmt_If:
                return $this->traverseArray(array($node->cond, $node->stmts, $node->elseifs, $node->else), $node);

            case $node instanceof \PHPParser_Node_Stmt_Foreach:
                return $this->traverseArray(array($node->expr, $node->keyVar, $node->valueVar, $node->stmts), $node);

            case $node instanceof \PHPParser_Node_Expr_PostDec:
            case $node instanceof \PHPParser_Node_Expr_PostInc:
                return;
        }

        if (null !== $right) {
            return $this->traverseInternal($right, $node);
        }

        // Consider all children as right side.
        return $this->traverseArray(iterator_to_array($node), $node);
    }

    private function traverseArray(array $nodes, \PHPParser_Node $parent)
    {
        foreach ($nodes as $subNode) {
            if (is_array($subNode)) {
                foreach ($subNode as $aSubNode) {
                    if (!$aSubNode instanceof \PHPParser_Node) {
                        continue;
                    }

                    if (false === $this->traverseInternal($aSubNode, $parent)) {
                        return false;
                    }
                }
            } else if ($subNode instanceof \PHPParser_Node) {
                if (false === $this->traverseInternal($subNode, $parent)) {
                    return false;
                }
            }
        }
    }
}