<?php

namespace JMS\PhpManipulator\Tests;

use JMS\PhpManipulator\PhpParser\ParseUtils;
use JMS\PhpManipulator\SimultaneousTokenAstStream;
use Scrutinizer\PhpAnalyzer\PhpParser\NodeUtil;

class SimultaneousTokenAndAstStreamTest extends \PHPUnit_Framework_TestCase
{
    private $stream;

    /**
     * @dataProvider getRunThroughTests
     */
    public function testRunsThrough($file)
    {
        $this->loadInput($file);
    }

    public function getRunThroughTests()
    {
        return array(
            array('static_call.php'),
            array('dynamic_method_name.php'),
            array('switch.php'),
            array('case_condition_order.php'),
            array('do_while.php'),
            array('static_variable.php'),
            array('static_var_with_assign.php'),
            array('string_offset.php'),
            array('assign.php'),
            array('assign_ref.php'),
            array('bitwise_operations.php'),
            array('dynamic_var.php'),
            array('assign_list.php'),
            array('refs.php'),
            array('array_typehint.php'),
        );
    }

    protected function setUp()
    {
        $this->stream = new SimultaneousTokenAstStream();
        $this->stream->getTokenStream()->setIgnoreComments(false);
        $this->stream->getTokenStream()->setIgnoreWhitespace(false);
    }

    private function loadInput($filename)
    {
        if ( ! is_file($path = __DIR__.'/Fixture/'.$filename)) {
            throw new \InvalidArgumentException(sprintf('The fixture file "%s" does not exist.', $filename));
        }

        $this->stream->setInput(file_get_contents($path));
    }
}