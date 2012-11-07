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

namespace JMS\PhpManipulator\TokenStream;

/**
 * Represents literals.
 *
 * These are parsed by {@code token_get_all} to mere strings.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class LiteralToken extends AbstractToken
{
    private $value;
    private $line;

    public function __construct($value, $line)
    {
        $this->value = $value;
        $this->line = $line;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getLine()
    {
        return $this->line;
    }

    public function setLine($line)
    {
        $this->line = $line;
    }

    public function getEndLine()
    {
        return $this->line;
    }

    public function getContent()
    {
        return $this->value;
    }

    public function isBlockOpener()
    {
        return '(' === $this->value
                    || '[' === $this->value
                    || '{' === $this->value;
    }

    public function isSameType(AbstractToken $that)
    {
        if (!$that instanceof LiteralToken) {
            return false;
        }

        return $this->value === $that->value;
    }

    public function isClosing(AbstractToken $token)
    {
        if ($token instanceof PhpToken
                && $token->getType() === T_CURLY_OPEN
                && $this->value === '}') {
            return true;
        }

        if (!$token instanceof LiteralToken) {
            return false;
        }

        switch ($this->value) {
            case '}':
                return '{' === $token->value;

            case ')':
                return '(' === $token->value;

            case ']':
                return '[' === $token->value;

            default:
                return false;
        }
    }

    protected function matchesInternal($value)
    {
        return $this->value === $value;
    }

    public function equals(AbstractToken $that)
    {
        if ( ! $that instanceof LiteralToken) {
            return false;
        }

        return $this->value === $that->value;
    }

    public function __toString()
    {
        return sprintf('Literal("%s", %d)', $this->value, $this->line);
    }
}