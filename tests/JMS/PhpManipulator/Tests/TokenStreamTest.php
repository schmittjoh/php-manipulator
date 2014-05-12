<?php

namespace JMS\PhpManipulator\Tests;

use JMS\PhpManipulator\TokenStream;
use JMS\PhpManipulator\TokenStream\LiteralToken;
use JMS\PhpManipulator\TokenStream\PhpToken;
use JMS\PhpManipulator\TokenStream\MarkerToken;

class TokenStreamTest extends \PHPUnit_Framework_TestCase
{
    private $stream;
    private $debugStream;

    public function testOpenTag1()
    {
        $this->setCode('<?php ;');
        $this->assertTokens(array(
            'MarkerToken(id = ^)',
            'PhpToken(T_OPEN_TAG, "<?php", 1)',
            'PhpToken(T_WHITESPACE, " ", 1)',
            'Literal(";", 1)',
            'MarkerToken(id = $)',
        ));
    }

    public function testOpenTag2()
    {
        if ('1' !== ini_get('short_open_tag')) {
            $this->markTestSkipped('Short Open Tags disabled in php.ini.');
        }

        $this->setCode('<? ;');
        $this->assertTokens(array(
            'MarkerToken(id = ^)',
            'PhpToken(T_OPEN_TAG, "<?", 1)',
            'PhpToken(T_WHITESPACE, " ", 1)',
            'Literal(";", 1)',
            'MarkerToken(id = $)',
        ));
    }

    public function testOpenTag3()
    {
        $this->setCode("<?php\n\n;");
        $this->assertTokens(array(
            'MarkerToken(id = ^)',
            'PhpToken(T_OPEN_TAG, "<?php", 1)',
            'PhpToken(T_WHITESPACE, "\n", 1)',
            'PhpToken(T_WHITESPACE, "\n", 2)',
            'Literal(";", 3)',
            'MarkerToken(id = $)',
        ));
    }

    public function testWhitespace()
    {
        $this->setCode("<?php \n    \n    ");
        $this->assertTokens(array(
            'MarkerToken(id = ^)',
            'PhpToken(T_OPEN_TAG, "<?php", 1)',
            'PhpToken(T_WHITESPACE, " \n", 1)',
            'PhpToken(T_WHITESPACE, "    \n", 2)',
            'PhpToken(T_WHITESPACE, "    ", 3)',
            'MarkerToken(id = $)',
        ));
    }

    /**
     * @dataProvider whitespaceConfigurationProvider
     */
    public function testGetWhitespace($flag)
    {
        $this->stream->setIgnoreWhitespace($flag);
        $this->setCode("<?php \n  echo     'foobar';\n        exit;");
        while ($this->stream->moveNext()) {
            if( !$this->stream->token instanceof MarkerToken ) {
                $this->assertEmpty(trim($this->stream->token->getIndentation()));
                $this->assertEmpty(trim($this->stream->token->getLineIndentation()));
                $this->assertEmpty(trim($this->stream->token->getWhitespaceBefore()));
                $this->assertEmpty(trim($this->stream->token->getWhitespaceAfter()));
            }
        }
    }

    public function whitespaceConfigurationProvider()
    {
        return [
            [true ],
            [false]
        ];
    }

    public function testGetLineContent()
    {
        $this->setCode("<?php \n echo 'foobar';\n    exit;");

        $this->assertEquals("<?php \n", $this->stream->getLineContent(1));
        $this->assertEquals(" echo 'foobar';\n", $this->stream->getLineContent(2));
        $this->assertEquals("    exit;", $this->stream->getLineContent(3));
    }

    public function testMoveNext()
    {
        $this->stream->setIgnoreWhitespace(false);
        $this->setCode("<?php if (\$a) { }");

        $this->assertTokenSequence(array(
            'MarkerToken(id = ^)',
            'PhpToken(T_OPEN_TAG, "<?php", 1)',
            'PhpToken(T_WHITESPACE, " ", 1)',
            'PhpToken(T_IF, "if", 1)',
            'PhpToken(T_WHITESPACE, " ", 1)',
            'Literal("(", 1)',
            'PhpToken(T_VARIABLE, "$a", 1)',
            'Literal(")", 1)',
            'PhpToken(T_WHITESPACE, " ", 1)',
            'Literal("{", 1)',
            'PhpToken(T_WHITESPACE, " ", 1)',
            'Literal("}", 1)',
            'MarkerToken(id = $)',
        ));
    }

    public function testSkipUntil()
    {
        $this->setCode("<?php if (\$foo === 'foo') { }");
        $this->assertNull($this->stream->previous);
        $this->assertNull($this->stream->token);
        $this->assertToken('MarkerToken(id = ^)', $this->stream->next);

        $this->stream->moveNext();
        $this->assertToken('PhpToken(T_OPEN_TAG, "<?php", 1)', $this->stream->next);

        $this->stream->skipUntil(T_IF);
        $this->assertToken('PhpToken(T_IF, "if", 1)', $this->stream->token);

        $this->stream->skipUntil('{');
        $this->assertToken('Literal("{", 1)', $this->stream->token);
    }

    protected function setUp()
    {
        $this->stream = new TokenStream();
    }

    private function assertTokenSequence(array $seq)
    {
        $previous = $token = $next = null;
        foreach ($seq as $newNext) {
            $previous = $token;
            $token = $next;
            $next = $newNext;

            $this->assertState($previous, $token, $next);
            $this->assertTrue($this->stream->moveNext());
        }

        $previous = $token;
        $token = $next;
        $next = null;
        $this->assertState($previous, $token, $next);
        $this->assertFalse($this->stream->moveNext());

        $previous = $token;
        $token = null;
        $this->assertState($previous, $token, $next);
        $this->assertFalse($this->stream->moveNext());
    }

    private function assertState($previous, $token, $next)
    {
        if (null === $previous) {
            $this->assertNull($this->stream->previous);
        } else {
            $this->assertEquals($previous, (string) $this->stream->previous);
        }

        if (null === $token) {
            $this->assertNull($this->stream->token);
        } else {
            $this->assertEquals($token, (string) $this->stream->token);
        }

        if (null === $next) {
               $this->assertNull($this->stream->next);
           } else {
               $this->assertEquals($next, (string) $this->stream->next);
           }
    }

    private function assertToken($expected, $actual = null)
    {
        $this->assertEquals((string) $expected, (string) ($actual ?: $this->stream->token));
    }

    private function assertTokens(array $expectedTokens)
    {
        $expectedStream = '';
        foreach ($expectedTokens as $i => $token) {
            $expectedStream .= $i.": ".$token."\n";
        }

        $this->assertEquals($expectedStream, $this->debugStream);
    }

    private function newToken()
    {
        switch (func_num_args()) {
            case 1:
                return new LiteralToken(func_get_arg(0));

            case 3:
                return new PhpToken(func_get_args());

            default:
                throw new \InvalidArgumentException('Received an unsupported number of arguments.');
        }
    }

    private function setCode($code)
    {
        $this->stream->setCode($code);

        $this->debugStream = '';
        foreach ($this->stream->getTokens() as $i => $token) {
            $this->debugStream .= $i.": ".$token."\n";
        }
    }
}

