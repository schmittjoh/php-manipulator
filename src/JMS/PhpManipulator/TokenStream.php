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

use LogicException;

/**
 * A helper class to work with the token stream.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class TokenStream
{
    /** @var AbstractToken|null */
    public $previous;

    /** @var AbstractToken|null */
    public $token;

    /** @var AbstractToken|null */
    public $next;

    private $i;
    private $tokens;
    private $peekCount;
    private $ignoreWhitespace = true;
    private $ignoreComments = true;

    private $ignoredTokens;
    private $ignoredTokensFunction;
    private $tokensFunction;
    private $afterMoveCallback;

    private $locked = false;

    public function setIgnoreComments($bool)
    {
        if($this->locked) throw new LogicException("Unable to alter ignore_comments configuration, stream is already set.");
        $this->ignoreComments = $bool;
    }

    public function setIgnoredTokensFunction($func)
    {
        if ( ! is_callable($func)) {
            throw new \InvalidArgumentException('$func must be a callable.');
        }

        $this->ignoredTokensFunction = $func;
    }

    public function setTokensFunction($func)
    {
        if ( ! is_callable($func)) {
            throw new \InvalidArgumentException('$func must be a callable.');
        }

        $this->tokensFunction = $func;
    }

    public function setAfterMoveCallback($func)
    {
        if (!is_callable($func)) {
            throw new \InvalidArgumentException('$func must be a callable.');
        }

        $this->afterMoveCallback = $func;
    }

    public function getLineContent($line)
    {
        $content = '';
        $exists  = false;
        foreach ($this->tokens as $token) {
            if ($line !== $token->getLine()) {
                continue;
            }

            $exists = true;
            $content .= $token->getContent();
        }

        if (!$exists) {
            throw new \InvalidArgumentException(sprintf('The line "%d" does not exist in the code.', $line));
        }

        return $content;
    }

    public function getTokensFunction()
    {
        return $this->tokensFunction;
    }

    public function setIgnoreWhitespace($bool)
    {
        if($this->locked) throw new LogicException("Unable to alter ignore_whitespace configuration, stream is already set.");
        $this->ignoreWhitespace = (boolean) $bool;
    }

    public function setCodeFragment($fragment)
    {
        if (false === strpos($fragment, '<php')) {
            $fragment = '<?php'.$fragment;
        }

        $this->tokens = $this->normalizeTokens(@token_get_all($fragment));
        $this->addMarkerTokens();

        $this->reset();
    }

    public function setCode($code)
    {
        $this->locked = true;
        $this->tokens = $this->normalizeTokens(@token_get_all($code));
        $this->addMarkerTokens();

        $this->reset();
    }

    /**
     * Normalizes the original PHP token stream into a format which is more
     * usable for analysis.
     *
     * @param array $tokens the format returned by ``token_get_all``
     *
     * @return array the normalized tokens
     */
    private function normalizeTokens(array $tokens)
    {
        $nTokens = array();
        for ($i=0,$c=count($tokens); $i<$c; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                $nTokens[] = new LiteralToken($token, end($nTokens)->getEndLine());
                continue;
            }

            switch ($token[0]) {
                case T_WHITESPACE:
                    $lines = explode("\n", $token[1]);
                    for ($j=0,$k=count($lines); $j<$k; $j++) {
                        $line = $lines[$j].($j+1 === $k ? '' : "\n");

                        if ($j+1 === $k && '' === $line) {
                            break;
                        }

                        $nTokens[] = new PhpToken(array(T_WHITESPACE, $line, $token[2] + $j));
                    }
                    break;

                default:
                    // remove any trailing whitespace of the token
                    if (preg_match('/^(.*?)(\s+)$/', $token[1], $match)) {
                        $nTokens[] = new PhpToken(array($token[0], $match[1], $token[2]));

                        // if the next token is whitespace, change it
                        if (isset($tokens[$i+1]) && !is_string($tokens[$i+1])
                                && T_WHITESPACE === $tokens[$i+1][0]) {
                            $tokens[$i+1][1] = $match[2].$tokens[$i+1][1];
                            $tokens[$i+1][2] = $token[2];
                        } else {
                            $nTokens[] = new PhpToken(array(T_WHITESPACE, $match[2], $token[2]));
                        }
                    } else {
                        $nTokens[] = new PhpToken($token);
                    }
            }
        }

        return $nTokens;
    }

    /**
     * Inserts a new token before the passed token.
     *
     * @param \JMS\PhpManipulator\TokenStream\AbstractToken $token
     * @param string|integer $type either one of PHP's T_??? constants, or a literal
     * @param string|null $value When type is a T_??? constant, this should be its value.
     */
    public function insertBefore(AbstractToken $token, $type, $value = null)
    {
        $this->insertAllTokensBefore($token, array(array($type, $value, $token->getLine())));
    }

    /**
     * @param TokenStream\AbstractToken $token
     * @param array<array<string|integer>> $tokens
     */
    public function insertAllBefore(AbstractToken $token, array $tokens)
    {
        $newTokens = array();
        $line = $token->getLine();
        foreach ($tokens as $rawToken) {
            $newTokens[] = array($rawToken[0], isset($rawToken[1]) ? $rawToken[1] : null, $line);
            if (isset($rawToken[1])) {
                $line += substr_count($rawToken[1], "\n");
            }
        }

        $this->insertAllTokensBefore($token, $newTokens);
    }

    /**
     * @param TokenStream\AbstractToken $token
     * @param array $tokens the format returned by ``token_get_all``
     */
    private function insertAllTokensBefore(AbstractToken $token, array $tokens)
    {
        $normalizedTokens = $this->normalizeTokens($tokens);

        $lineOffset = 0;
        foreach ($normalizedTokens as $newToken) {
            $lineOffset += substr_count($newToken->getValue(), "\n");
        }

        foreach ($this->tokens as $i => $cToken) {
            if ($token !== $cToken) {
                continue;
            }

            // Compensate for the token that is being inserted, so that we do not visit a token twice.
            if ($i <= $this->i) {
                $this->i += count($normalizedTokens);
            }

            $this->tokens = array_merge(
                array_slice($this->tokens, 0, $i),
                $normalizedTokens,
                array_slice($this->tokens, $i)
            );

            for ($k=$i,$c=count($this->tokens); $k<$c; $k++) {
                $this->tokens[$i]->setAttribute('position', $k);
            }

            for ($k=0,$c=count($normalizedTokens); $k<$c; $k++) {
                if (0 === $k) {
                    $token->getPreviousToken()->get()->setNextToken($normalizedTokens[$k]);
                    $normalizedTokens[$k]->setPreviousToken($token->getPreviousToken()->get());
                }

                if ($k+1 === $c) {
                    $token->setPreviousToken($normalizedTokens[$k]);
                    $normalizedTokens[$k]->setNextToken($token);
                }

                if ($k > 0 && $k+1 < $c) {
                    $normalizedTokens[$k]->setPreviousToken($normalizedTokens[$k-1]);
                    $normalizedTokens[$k-1]->setNextToken($normalizedTokens[$k]);

                    $normalizedTokens[$k]->setNextToken($normalizedTokens[$k+1]);
                    $normalizedTokens[$k+1]->setPreviousToken($normalizedTokens[$k-1]);
                }
            }

            if ($lineOffset > 0) {
                $nextToken = $token;
                do {
                    $nextToken->setLine($nextToken->getLine() + $lineOffset);
                } while (null !== $nextToken = $nextToken->getNextToken()->getOrElse(null));
            }

            break;
        }
    }

    public function reset()
    {
        $this->i = -1;
        $this->token = $this->previous = $this->next = null;
        $this->ignoredTokens = array();

        $previous = null;
        foreach ($this->tokens as $i => $token) {
            $token->setAttribute('position', $i);

            if ($this->isIgnored($token)) {
                continue;
            }

            if ($previous) {
                $previous->setNextToken($token);
                $token->setPreviousToken($previous);
            }
            $previous = $token;
        }

        $this->moveNext();
    }

    public function skipCurrentBlock()
    {
        if (!$this->token->isBlockOpener()) {
            throw new \RuntimeException(sprintf('The token "%s" is not a block opener.', $this->token));
        }

        $openerToken = $this->token;
        $opened = 1;
        while ($this->moveNext()) {
            if ($this->token->isSameType($openerToken)) {
                $opened += 1;
            } else if ($this->token->isClosing($openerToken)) {
                $opened -= 1;

                if (0 === $opened) {
                    return;
                }
            }
        }

        throw new \RuntimeException('Did not find end of block.');
    }

    public function getClosingToken()
    {
        if (!$this->token->isBlockOpener()) {
            throw new \RuntimeException(sprintf('The token "%s" is not a block opener.', $this->token));
        }

        $opened = 1;
        while ($peekToken = $this->peek()) {
            if ($peekToken->isSameType($this->token)) {
                $opened += 1;
            } else if ($peekToken->isClosing($this->token)) {
                $opened -= 1;

                if (0 === $opened) {
                    return $peekToken;
                }
            }
        }

        throw new \RuntimeException('Did not find end of block.');
    }

    public function skipBlock()
    {
        $this->moveNext();
        $this->skipCurrentBlock();
    }

    public function getTokens()
    {
        return $this->tokens;
    }

    public function skipUntil($token)
    {
        $found = false;
        while ($this->moveNext()) {
            if ($this->token->matches($token)) {
                $found = true;
                break;
            }
        }

        return $found;
    }

    public function isIgnored(AbstractToken $token)
    {
        if ($this->ignoreComments && $token->isComment()) {
            return true;
        }

        if ($this->ignoreWhitespace && $token->isWhitespace()) {
            return true;
        }

        return false;
    }

    public function peek()
    {
        if (isset($this->tokens[$this->i + $this->peekCount])) {
            return $this->tokens[$this->i + ($this->peekCount++)];
        }

        return null;
    }

    public function moveNext()
    {
        if (null !== $this->token && $this->tokensFunction) {
            call_user_func($this->tokensFunction, $this->token, $this);
        }

        $this->previous = $this->token;
        $this->token = $this->next;

        if ($this->ignoredTokensFunction) {
            foreach ($this->ignoredTokens as $ignoredToken) {
                call_user_func($this->ignoredTokensFunction, $ignoredToken);
            }
        }

        $this->peekCount = 0;
        $this->next = null;
        $this->ignoredTokens = array();
        while (isset($this->tokens[$this->i + 1])) {
            $nextToken = $this->tokens[++$this->i];

            if ($this->isIgnored($nextToken)) {
                $this->ignoredTokens[] = $nextToken;
                continue;
            }

            $this->next = $nextToken;
            break;
        }

        if (null !== $this->afterMoveCallback) {
            call_user_func($this->afterMoveCallback, $this->token, $this);
        }

        return null !== $this->token;
    }

    private function addMarkerTokens()
    {
        // Add marker tokens for the beginning and end of the file.
        array_unshift($this->tokens, new TokenStream\MarkerToken('^', 1));
        array_push($this->tokens, new TokenStream\MarkerToken('$', end($this->tokens)->getLine()));
    }
}