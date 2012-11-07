<?php

namespace JMS\PhpManipulator\Tests\PhpParser;

use JMS\PhpManipulator\PhpParser\ParseUtils;
use JMS\PhpManipulator\PhpParser\InOrderTraversal;

class InorderNodeTraversalTest extends \PHPUnit_Framework_TestCase
{
    private $nodes = array();

    public function testArrayDimFetch()
    {
        $this->assertOrder('$a[0] = "foo";', array(
            'PHPParser_Node_Expr_Variable',
            'PHPParser_Node_Expr_ArrayDimFetch',
            'PHPParser_Node_Scalar_LNumber',
            'PHPParser_Node_Expr_Assign',
            'PHPParser_Node_Scalar_String',
        ));
    }

    public function testForeach()
    {
        $this->assertOrder('foreach ($a as $b) { echo "foo"; }', array(
            'PHPParser_Node_Stmt_Foreach',
            'PHPParser_Node_Expr_Variable',
            'PHPParser_Node_Expr_Variable',
            'JMS\PhpManipulator\PhpParser\BlockNode',
            'PHPParser_Node_Stmt_Echo',
            'PHPParser_Node_Scalar_String',
        ));
    }

    public function testFor()
    {
        $this->assertOrder('for ($i=0; $i < 10; $i++) { echo "foo"; }', array(
            'PHPParser_Node_Stmt_For',
            'PHPParser_Node_Expr_Variable',
            'PHPParser_Node_Expr_Assign',
            'PHPParser_Node_Scalar_LNumber',
            'PHPParser_Node_Expr_Variable',
            'PHPParser_Node_Expr_Smaller',
            'PHPParser_Node_Scalar_LNumber',
            'PHPParser_Node_Expr_Variable',
            'PHPParser_Node_Expr_PostInc',
            'JMS\PhpManipulator\PhpParser\BlockNode',
            'PHPParser_Node_Stmt_Echo',
            'PHPParser_Node_Scalar_String',
        ));
    }

    public function testClosure()
    {
        $this->assertOrder('function($foo) use ($bar) { };', array(
            'PHPParser_Node_Expr_Closure',
            'PHPParser_Node_Param',
            'PHPParser_Node_Expr_ClosureUse',
            'JMS\PhpManipulator\PhpParser\BlockNode',
        ));
    }

    public function testIf()
    {
        $this->assertOrder('if (null !== $foo) { 5; }', array(
            'PHPParser_Node_Stmt_If',
            'PHPParser_Node_Expr_ConstFetch',
            'PHPParser_Node_Name',
            'PHPParser_Node_Expr_NotIdentical',
            'PHPParser_Node_Expr_Variable',
            'JMS\PhpManipulator\PhpParser\BlockNode',
            'PHPParser_Node_Scalar_LNumber',
        ));
    }

    public function testDynamicMethodCall()
    {
        $this->assertOrder("\$a->{'foo'}(); \$a;", array(
            'JMS\PhpManipulator\PhpParser\BlockNode',
            'PHPParser_Node_Expr_Variable',
            'PHPParser_Node_Expr_MethodCall',
            'PHPParser_Node_Scalar_String',
            'PHPParser_Node_Expr_Variable',
        ));
    }

    public function testSwitch()
    {
        $this->assertOrder('switch ($a) { case "foo": break; default: $b; }', array(
            'PHPParser_Node_Stmt_Switch',
            'PHPParser_Node_Expr_Variable',
            'PHPParser_Node_Stmt_Case',
            'PHPParser_Node_Scalar_String',
            'JMS\PhpManipulator\PhpParser\BlockNode',
            'PHPParser_Node_Stmt_Break',
            'PHPParser_Node_Stmt_Case',
            'JMS\PhpManipulator\PhpParser\BlockNode',
            'PHPParser_Node_Expr_Variable',
        ));
    }

    public function testSwitch2()
    {
        $this->assertOrder('        switch ($class->name) {
            case \'Metadata\Tests\Fixtures\ComplexHierarchy\SubClassB\':
                new PropertyMetadata($class->name, \'baz\');
                break;
        }', array(
            'PHPParser_Node_Stmt_Switch',
            'PHPParser_Node_Expr_Variable',
            'PHPParser_Node_Expr_PropertyFetch',
            'PHPParser_Node_Stmt_Case',
            'PHPParser_Node_Scalar_String',
            'JMS\PhpManipulator\PhpParser\BlockNode',
            'PHPParser_Node_Expr_New',
            'PHPParser_Node_Name_FullyQualified',
            'PHPParser_Node_Arg',
            'PHPParser_Node_Expr_Variable',
            'PHPParser_Node_Expr_PropertyFetch',
            'PHPParser_Node_Arg',
            'PHPParser_Node_Scalar_String',
            'PHPParser_Node_Stmt_Break',
        ));
    }

    public function testDoWhile()
    {
        $this->assertOrder('do { $a; } while ("foo");', array(
            'PHPParser_Node_Stmt_Do',
            'JMS\PhpManipulator\PhpParser\BlockNode',
            'PHPParser_Node_Expr_Variable',
            'PHPParser_Node_Scalar_String',
        ));
    }

    private function assertOrder($code, array $expectedOrder)
    {
        $this->traverse('<?php '.$code);
        $this->assertEquals($expectedOrder, array_map('get_class', $this->nodes));
    }

    private function traverse($code)
    {
        $ast = ParseUtils::parse($code);

        $nodes = &$this->nodes;
        InOrderTraversal::traverseWithCallback($ast, function($node) use (&$nodes) {
            $nodes[] = $node;
        });
    }
}