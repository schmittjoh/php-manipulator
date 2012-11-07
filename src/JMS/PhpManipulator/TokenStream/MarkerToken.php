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
 * Marker Token.
 *
 * A marker token does not contain any actual content, and can be placed anywhere
 * in the token stream to allow easy jumping to a certain location.
 *
 * Notable marker tokens are ``^`` to indicate the start of a file, and ``$``
 * to mark its end. These two tokens do not apply to line start/endings.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class MarkerToken extends AbstractToken
{
    private $id;
    private $line;

    /**
     * @param string $id
     */
    public function __construct($id, $line)
    {
        $this->id = $id;
    }

    protected function matchesInternal($matcher)
    {
        return $this->id === $matcher;
    }

    public function __toString()
    {
        return sprintf('MarkerToken(id = %s)', $this->id);
    }

    public function equals(AbstractToken $token)
    {
        if ( ! $token instanceof MarkerToken) {
            return false;
        }

        if ($this->id !== $token->id) {
            return false;
        }

        if ($this->line !== $token->line) {
            return false;
        }

        return true;
    }

    public function getContent()
    {
        return '';
    }

    public function getEndLine()
    {
        return $this->line;
    }

    public function getLine()
    {
        return $this->line;
    }

    public function isBlockOpener()
    {
        return false;
    }

    public function isSameType(AbstractToken $token)
    {
        if ( ! $token instanceof MarkerToken) {
            return false;
        }

        return $this->id === $token->id;
    }

    public function setLine($line)
    {
        $this->line = $line;
    }
}