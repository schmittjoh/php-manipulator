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

use PhpOption\None;
use PhpOption\Some;
use PhpOption\Option;

/**
 * Abstract Token.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class AbstractToken
{
    protected $previousToken;
    protected $nextToken;
    private $attributes = array();

    public function setPreviousToken(AbstractToken $token)
    {
        $this->previousToken = $token;
    }

    public function setNextToken(AbstractToken $token)
    {
        $this->nextToken = $token;
    }

    public function getPreviousToken()
    {
        if (null === $this->previousToken) {
            return None::create();
        }

        return new Some($this->previousToken);
    }

    public function getNextToken()
    {
        if (null === $this->nextToken) {
            return None::create();
        }

        return new Some($this->nextToken);
    }

    /**
     * Finds the next token that satisfies the passed matcher.
     *
     * Eligible matchers are:
     *
     * - T_??? constants such as T_WHITESPACE
     * - Literal values such as ";"
     * - A Closure which receives an AbstractToken as first argument
     * - Some special built-in matchers: "END_OF_LINE", "END_OF_NAME" (see matches())
     *
     * @param string|integer|\Closure $matcher
     *
     * @return Option<AbstractToken>
     */
    public function findNextToken($matcher)
    {
        $nextToken = $this->nextToken;
        while (null !== $nextToken) {
            if ($nextToken->matches($matcher)) {
                return new Some($nextToken);
            }

            $nextToken = $nextToken->nextToken;
        }

        return None::create();
    }

    /**
     * Finds the previous token that satisfies the passed matcher.
     *
     * Eligible matchers are:
     *
     * - T_??? constants such as T_WHITESPACE
     * - Literal values such as ";"
     * - A Closure which receives an AbstractToken as first argument
     * - Some special built-in matchers: "END_OF_LINE", "END_OF_NAME", etc. (see matches())
     *
     * @param string|integer|\Closure $matcher
     *
     * @return Option<AbstractToken>
     */
    public function findPreviousToken($matcher)
    {
        $previousToken = $this->previousToken;
        while (null !== $previousToken) {
            if ($previousToken->matches($matcher)) {
                return new Some($previousToken);
            }

            $previousToken = $previousToken->previousToken;
        }

        return None::create();
    }

    public function getNbTokensUntil($matcher)
    {
        $count = 0;
        $nextToken = $this->nextToken;
        while (null !== $nextToken) {
            if ($nextToken->matches($matcher)) {
                return $count;
            }

            $count += 1;
            $nextToken = $nextToken->nextToken;
        }

        return -1;
    }

    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function removeAttribute($key)
    {
        unset($this->attributes[$key]);
    }

    public function hasAttribute($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * @param string $key
     *
     * @return Option<*>
     */
    public function getAttribute($key)
    {
        if ( ! isset($this->attributes[$key])) {
            return None::create();
        }

        return new Some($this->attributes[$key]);
    }

    /**
     * Performs a thorough equality check.
     *
     * @param AbstractToken $token
     *
     * @return boolean
     */
    abstract public function equals(AbstractToken $token);

    /**
     * Checks whether the passed token is of the same type.
     *
     * In contrast to equals, this check is not as thorough in that
     * the token might have a slightly different value, and may be
     * placed on a different line.
     *
     * @param AbstractToken $token
     *
     * @return boolean
     */
    abstract public function isSameType(AbstractToken $token);

    /**
     * Whether the given token is closing this token.
     *
     * For example, a "(" would be closed by a ")".
     *
     * @param AbstractToken $token
     * @return boolean
     */
    public function isClosing(AbstractToken $token)
    {
        return false;
    }

    /**
     * Returns the indentation necessary to reach the start of this token on a
     * new line.
     *
     * It is important to note that this also supports smart tabs as it uses whatever
     * is used for indenting the current lines, and only then pads this with spaces.
     *
     * @return string
     */
    public function getIndentation()
    {
        $indentation = $this->getLineIndentation();

        if($this->isFirstTokenOnLine() AND $this->matches(T_WHITESPACE)) {
            $indentation = '';
        }

        return $indentation . str_repeat(' ', $this->getStartColumn() - strlen($indentation));
    }

    public function getStartColumn()
    {
        return strlen($this->getFirstTokenOnLine()->getContentUntil($this));
    }

    /**
     * Returns the whitespace after this token.
     *
     * @return string
     */
    public function getWhitespaceAfter()
    {
        return $this->getContentBetween($this->findNextToken('NO_WHITESPACE')->get());
    }

    /**
     * Returns the whitespace before this token.
     *
     * @return string
     */
    public function getWhitespaceBefore()
    {
        return $this->findPreviousToken('NO_WHITESPACE')->get()->getContentBetween($this);
    }

    /**
     * @return string
     */
    abstract public function __toString();

    /**
     * Returns the starting line of the token.
     *
     * @return integer
     */
    abstract public function getLine();

    /**
     * @return string
     */
    abstract public function getContent();

    /**
     * @param integer $line
     *
     * @return void
     */
    abstract public function setLine($line);

    /**
     * Returns the ending line of the token.
     *
     * @return integer
     */
    abstract public function getEndLine();

    /**
     * Returns the content from this Token until the passed token.
     *
     * The content of this token is included, and the content of the passed token
     * is excluded.
     *
     * @param \JMS\CodeReview\Analysis\PhpParser\TokenStream\AbstractToken $token
     *
     * @return string
     */
    public function getContentUntil(AbstractToken $token)
    {
        if ($this === $token) {
            return '';
        }

        $content = $this->getContent();
        $next = $this->nextToken;

        while ($next && $next !== $token) {
            $content .= $next->getContent();
            $next = $next->nextToken;
        }

        if (null === $next) {
            throw new \RuntimeException(sprintf('Could not find token "%s".', $token));
        }

        return $content;
    }

    public function getContentUntilIncluding(AbstractToken $token)
    {
        return $this->getContentUntil($token).$token->getContent();
    }

    /**
     * Returns the content between this token, and the passed token.
     *
     * In contrast to ``getContentUntil``, the content of this token is not included.
     *
     * @param \JMS\CodeReview\Analysis\PhpParser\TokenStream\AbstractToken $token
     *
     * @return string
     */
    public function getContentBetween(AbstractToken $token)
    {
        $content = '';
        $next = $this->nextToken;

        while ($next && $next !== $token) {
            $content .= $next->getContent();
            $next = $next->nextToken;
        }

        if (null === $next) {
            throw new \RuntimeException(sprintf('Could not find token "%s".', $token));
        }

        return $content;
    }

    /**
     * Gets the content from this token until that token.
     *
     * It includes the content of that token, but not of this token.
     *
     * @param \JMS\CodeReview\Analysis\PhpParser\TokenStream\AbstractToken $that
     *
     * @return string
     */
    public function getContentBetweenIncluding(AbstractToken $that)
    {
        return $this->getContentBetween($that).$that->getContent();
    }

    /**
     * Returns the indentation used on the current line.
     *
     * @return string
     */
    public function getLineIndentation()
    {
        $first = $this->getFirstTokenOnLine();
        if ( ! $first->matches(T_WHITESPACE)) {
            return '';
        }

        return $first->getContentUntil($first->findNextToken('NO_WHITESPACE')->get());
    }

    public function getFirstTokenOnLine()
    {
        if ($this->isFirstTokenOnLine()) {
            return $this;
        }

        $prev = $this;
        while (null !== $prev = $prev->previousToken) {
            if ($prev->isFirstTokenOnLine()) {
                return $prev;
            }
        }

        throw new \LogicException('Could not find first token on this line.');
    }

    /**
     * Returns whether this token matches the given token.
     *
     * Note that the passed token is not an instance of AbstractToken,
     * but either a string (representing a literal), or an integer
     * (representing the value for one of PHPs T_??? constants).
     *
     * TODO: We should probably add a full-fledged expression syntax.
     *
     * @param string|integer|\Closure $matcher
     *
     * @return boolean
     */
    public final function matches($matcher)
    {
        // Handle negations of matchers.
        if (is_string($matcher) && substr($matcher, 0, 3) === 'NO_') {
            return ! $this->matches(substr($matcher, 3));
        }

        // Handle some special cases.
        if ($matcher === 'WHITESPACE_OR_COMMENT') {
            return $this->matches(T_WHITESPACE) || $this->matches(T_COMMENT)
                        || $this->matches(T_DOC_COMMENT);
        } else if ($matcher === 'COMMENT') {
            return $this->matches(T_COMMENT) || $this->matches(T_DOC_COMMENT);
        } else if ($matcher === 'WHITESPACE') {
            return $this->matches(T_WHITESPACE);
        } else if ($matcher === 'END_OF_LINE') {
            return $this->isLastTokenOnLine();
        } else if ($matcher === 'END_OF_NAME') {
            return ! $this->matches(T_STRING)
                        && ! $this->matches(T_NS_SEPARATOR);
        } else if ($matcher === 'END_OF_CALL') {
            $opened = 0;
            $matcher = function(AbstractToken $token) use (&$opened) {
                switch (true) {
                    case $token->matches(')'):
                        return 0 === ($opened--);

                    case $token->matches('('):
                        $opened += 1;
                    // fall through

                    default:
                        return false;
                }
            };
        }

        if ($matcher instanceof \Closure) {
            return $matcher($this);
        }

        return $this->matchesInternal($matcher);
    }

    /**
     * @param string|integer $matcher
     *
     * @return boolean
     */
    abstract protected function matchesInternal($matcher);

    abstract public function isBlockOpener();

    public function isFirstTokenOnLine()
    {
        if (null === $this->previousToken) {
            return true;
        }

        return $this->previousToken->getLine() !== $this->getLine();
    }

    public function isLastTokenOnLine()
    {
        if (null === $this->nextToken) {
            return true;
        }

        return $this->nextToken->getLine() !== $this->getLine();
    }

    public function isWhitespace()
    {
        return false;
    }

    public function isComment()
    {
        return false;
    }
}