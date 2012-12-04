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

use JMS\PhpManipulator\TokenStream\AbstractToken;
use JMS\PhpManipulator\TokenStream\LiteralToken;
use JMS\PhpManipulator\TokenStream\PhpToken;

// Define some constants which are not available on PHP 5.3. It does not really
// matter what values we assign here, as token_get_all will never return them, and
// just fail if forced to parse PHP 5.4 code, but it will allow us to use these
// constants safely in our code.
if ( ! defined('T_TRAIT')) {
    define('T_TRAIT', 100001);
    define('T_INSTEADOF', 100002);
    define('T_CALLABLE', 100003);
    define('T_TRAIT_C', 100004);
}

/**
 * This is an simultaneous stream implementation allowing access to the raw token
 * as well as the higher-level AST stream.
 *
 * Traversal is done on the raw PHP token stream, but the corresponding AST nodes
 * are always accessible as well.
 *
 * We use the token stream for traversal since we not always can map the token
 * nodes to AST nodes one-to-one since there sometimes simply is no equivalent.
 * The other direction is always true though. We can always point to a token
 * for any given AST node thus the traversal will not miss any.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SimultaneousTokenAstStream
{
    /** @var AbstractToken */
    public $token;

    /** @var AbstractToken */
    public $previousToken;

    /** @var AbstractToken */
    public $nextToken;

    /** @var \PHPParser_Node */
    public $node;

    /** @var \PHPParser_Node */
    public $previousNode;

    /** @var \PHPParser_Node */
    public $nextNode;

    private $tokenStream;
    private $astStream;

    /**
     * Map for translating tokens to respective AST classes.
     */
    private static $translationMap = array(
        T_IF => 'PHPParser_Node_Stmt_If',
        T_ELSEIF => 'PHPParser_Node_Stmt_ElseIf',
        T_ELSE => 'PHPParser_Node_Stmt_Else',
        T_CASE => 'PHPParser_Node_Stmt_Case',
        T_DEFAULT => 'PHPParser_Node_Stmt_Case',
        T_CATCH => 'PHPParser_Node_Stmt_Catch',
        T_TRY => 'PHPParser_Node_Stmt_TryCatch',
        T_THROW => 'PHPParser_Node_Stmt_Throw',
        T_NAMESPACE => 'PHPParser_Node_Stmt_Namespace',
        T_USE => array('PHPParser_Node_Stmt_Use', 'PHPParser_Node_Expr_ClosureUse', 'PHPParser_Node_Stmt_TraitUse'),
        T_NEW => 'PHPParser_Node_Expr_New',
        '{' => 'JMS\PhpManipulator\PhpParser\BlockNode',
        '=' => array('PHPParser_Node_Expr_Assign', 'PHPParser_Node_Expr_AssignRef', 'PHPParser_Node_Expr_AssignList'),
        '|' => 'PHPParser_Node_Expr_BitwiseOr',
        '^' => 'PHPParser_Node_Expr_BitwiseXor',
        '~' => 'PHPParser_Node_Expr_BitwiseNot',
        T_OBJECT_OPERATOR => array('PHPParser_Node_Expr_PropertyFetch', 'PHPParser_Node_Expr_MethodCall'),
        T_VARIABLE => array('PHPParser_Node_Expr_Variable', 'PHPParser_Node_Param', 'PHPParser_Node_Stmt_PropertyProperty',
                            'PHPParser_Node_Expr_ClosureUse', 'PHPParser_Node_Expr_StaticPropertyFetch', 'PHPParser_Node_Stmt_StaticVar'),
        T_CONSTANT_ENCAPSED_STRING => array('PHPParser_Node_Scalar_String'),
        T_CLASS => 'PHPParser_Node_Stmt_Class',
        T_INTERFACE => 'PHPParser_Node_Stmt_Interface',
        T_TRAIT => 'PHPParser_Node_Stmt_Trait',
        T_FUNCTION => array('PHPParser_Node_Stmt_Function', 'PHPParser_Node_Stmt_ClassMethod', 'PHPParser_Node_Expr_Closure'),
        T_CONST => array('PHPParser_Node_Stmt_ClassConst', 'PHPParser_Node_Stmt_Const'),
        T_LNUMBER => 'PHPParser_Node_Scalar_LNumber',
        T_DNUMBER => 'PHPParser_Node_Scalar_DNumber',
    );

    public function __construct()
    {
        $this->astStream   = new AstStream();
        $this->tokenStream = new TokenStream();

        $astStream = $this->astStream;
        $translationMap = self::$translationMap;
        $self = $this;
        $this->tokenStream->setAfterMoveCallback(function(AbstractToken $token = null) use ($astStream, $translationMap, $self) {
            if ($token instanceof TokenStream\MarkerToken) {
                return;
            }

            if ($token instanceof LiteralToken) {
                $char = $token->getValue();
            } else if ($token instanceof PhpToken) {
                // For T_USE that is related to variable imports of closures, we
                // bail out directly as there is no dedicated node for it.
                if ($token->matches(T_USE)
                        && ($astStream->node instanceof \PHPParser_Node_Expr_Closure
                            || $astStream->node instanceof \PHPParser_Node_Param)) {
                    return;
                }

                if ($token->matches(T_VARIABLE)) {
                    // For T_VARIABLE that is related to a catch statement, we also
                    // bail out as there again is no dedicated node for it.
                    if ($astStream->node instanceof \PHPParser_Node_Stmt_Catch) {
                        return;
                    }

                    if ($astStream->node instanceof \PHPParser_Node_Param && $astStream->node->type === 'array') {
                        return;
                    }
                }

                if ($token->matches(T_ARRAY)) {
                    $astStream->skipUnless(function(\PHPParser_Node $n) {
                        if ($n instanceof \PHPParser_Node_Expr_Array) {
                            return true;
                        }

                        if ($n instanceof \PHPParser_Node_Param && $n->type === 'array') {
                            return true;
                        }

                        return false;
                    });

                    return;
                }

                $char = $token->getType();
            } else if (null === $token) {
                $self->previousNode = $self->node;
                $self->node = null;
                $self->nextNode = null;

                return;
            } else {
                throw new \RuntimeException(sprintf('Unknown token class "%s".', get_class($token)));
            }

            if ('{' === $char) {
                if ($token->getPreviousToken()->get()->matches(T_OBJECT_OPERATOR)) {
                    return;
                }

                if ($self->nextNode instanceof \PHPParser_Node_Expr_ArrayDimFetch) {
                    $self->getAstStream()->skipUntil('PHPParser_Node_Expr_ArrayDimFetch');

                    return;
                }

                // This does not handle the case of an empty switch, but that
                // should more be a theoretical problem, and should not happen
                // in real code.
                $nextToken = $token->findNextToken('NO_WHITESPACE_OR_COMMENT')->get();
                if ($nextToken->matches(T_CASE) || $nextToken->matches(T_DEFAULT)) {
                    return;
                }
            }

            if ('=' === $char) {
                // The assignment of default values in parameters has no dedicated node.
                if ($self->node instanceof \PHPParser_Node_Param) {
                    return;
                }

                // Property initializers do not have a specific node.
                if ($self->node instanceof \PHPParser_Node_Stmt_PropertyProperty) {
                    return;
                }


                if ($self->node instanceof \PHPParser_Node_Stmt_ClassConst) {
                    return;
                }

                if ($self->node instanceof \PHPParser_Node_Stmt_Const) {
                    return;
                }
            }

            if ('&' === $char) {
                $previousToken = $token->findPreviousToken('NO_WHITESPACE_OR_COMMENT')->get();
                // Ignore assignments by reference as we already have handled that case above.
                if ($previousToken->matches('=')) {
                    return;
                }

                // Value as reference in foreach
                if ($previousToken->matches(T_AS) || $previousToken->matches(T_DOUBLE_ARROW)) {
                    return;
                }

                if ($self->node instanceof \PHPParser_Node_Stmt_Function
                        || $self->node instanceof \PHPParser_Node_Expr_Closure
                        || $self->node instanceof \PHPParser_Node_Stmt_ClassMethod
                        || $self->node instanceof \PHPParser_Node_Param) {
                    return;
                }

                // Move to the next Expr_BitwiseAnd, or Expr_ArrayItem which is assigned by ref,
                // or Arg which is passed by ref.
                $self->getAstStream()->skipUnless(function(\PHPParser_Node $node) {
                    if ($node instanceof \PHPParser_Node_Expr_BitwiseAnd) {
                        return true;
                    }

                    if ($node instanceof \PHPParser_Node_Expr_ArrayItem && $node->byRef) {
                        return true;
                    }

                    if ($node instanceof \PHPParser_Node_Arg && $node->byRef) {
                        return true;
                    }

                    return false;
                });

                return;
            }

            if (isset($translationMap[$char])) {
                $self->getAstStream()->skipUntil($translationMap[$char]);
            }
        });
    }

    public function getTokenStream()
    {
        return $this->tokenStream;
    }

    public function getAstStream()
    {
        return $this->astStream;
    }

    public function setInput($code, \PHPParser_Node $ast = null)
    {
        $this->astStream->setAst($ast ?: PhpParser\ParseUtils::parse($code));
        $this->tokenStream->setCode($code);

        $lastNode = null;
        while ($this->moveNext()) {
            if ($lastNode !== $this->node) {
                $this->node->setAttribute('start_token', $this->token);
                if (null !== $lastNode) {
                    $lastNode->setAttribute('end_token', $this->token->getPreviousToken()->get());
                }
            }
            $lastNode = $this->node;
        }
        $this->reset();
    }

    public function moveToToken(AbstractToken $token)
    {
        while ($this->token !== $token && $this->moveNext());

        if ($this->token !== $token) {
            throw new \InvalidArgumentException(sprintf('Could not find "%s", but reached end of stream.', $token));
        }
    }

    public function skipUntil($matcher)
    {
        while ($this->moveNext()) {
            if ($this->token->matches($matcher)) {
                return $this->token;
            }
        }

        return null;
    }

    /**
     * @param TokenStream\AbstractToken $token
     * @param string|integer $type
     * @param null|string $value
     */
    public function insertTokenBefore(AbstractToken $token, $type, $value = null)
    {
        $this->tokenStream->insertBefore($token, $type, $value);
    }

    /**
     * @param TokenStream\AbstractToken $token
     * @param array<array<integer|string>> $tokens
     */
    public function insertTokensBefore(AbstractToken $token, array $tokens)
    {
        $this->tokenStream->insertAllBefore($token, $tokens);
    }

    public function reset()
    {
        $this->tokenStream->reset();
        $this->astStream->reset();
    }

    public function moveNext()
    {
        $rs = $this->tokenStream->moveNext();
        $this->updateLocalProperties();

        return $rs;
    }

    private function updateLocalProperties()
    {
        $this->token = $this->tokenStream->token;
        $this->previousToken = $this->tokenStream->previous;
        $this->nextToken = $this->tokenStream->next;

        $this->node = $this->astStream->node;
        $this->previousNode = $this->astStream->previous;
        $this->nextNode = $this->astStream->next;
    }
}