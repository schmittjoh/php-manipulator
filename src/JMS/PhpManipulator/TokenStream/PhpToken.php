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
 * Represents a regular PHP token.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PhpToken extends AbstractToken
{
    private $type;
    private $value;
    private $line;
    private $endLine;

    public function __construct(array $token)
    {
        list($this->type, $this->value, $this->line) = $token;

        $this->endLine = $this->line + substr_count($this->value, "\n");
    }

    public function getType()
    {
        return $this->type;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
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
        return $this->endLine;
    }

    public function isWhitespace()
    {
        return T_WHITESPACE === $this->type;
    }

    public function isComment()
    {
        return T_COMMENT === $this->type || T_DOC_COMMENT === $this->type;
    }

    public function getContent()
    {
        return $this->value;
    }

    protected function matchesInternal($type)
    {
        return $this->type === $type;
    }

    public function isBlockOpener()
    {
        return T_CURLY_OPEN === $this->type;
    }

    public function isSameType(AbstractToken $that)
    {
        if (T_CURLY_OPEN === $this->type
                && $that instanceof LiteralToken
                && '{' === $that->getValue()) {
            return true;
        }

        if (!$that instanceof PhpToken) {
            return false;
        }

        return $this->type === $that->type;
    }

    public function equals(AbstractToken $that)
    {
        if (!$that instanceof PhpToken) {
            return false;
        }

        return $this->type === $that->type
                   && $this->value === $that->value
                   && $this->line === $that->line;
    }

    public function __toString()
    {
        return sprintf('PhpToken(%s, %s, %d)', token_name($this->type), json_encode($this->value), $this->line);
    }
}