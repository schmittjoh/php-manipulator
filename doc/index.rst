PHP Manipulator
===============
A helper library for analyzing and modifying PHP source files.

Installation
------------
PHP Manipulator can easily be installed via composer

.. code-block :: bash

    composer require jms/php-manipulator

or add it to your ``composer.json`` file.

Usage
-----
There are two representations of your source code that this library uses. The
first one is the token stream. This library internally uses ``token_get_all``,
and adds an object oriented abstraction on top of it. Also, it performs some
transformations on the original tokens to make them more suitable for analysis.

The second representation is the abstract syntax tree. It is a higher level
abstraction than the token stream; usually not suitable for modifying the source
code, but very useful for analysis.

The Token Stream
~~~~~~~~~~~~~~~~
The Token Stream can be used standalone for modifying PHP source files::

    use JMS\PhpManipulator\TokenStream;

    $stream = new TokenStream();
    $stream->setCode($sourceCode);

    while ($stream->moveNext()) {
        echo $stream->token->getContent();

        if ($stream->token->matches(T_CLASS)) {
            $extends = $stream->token->findNextToken(T_EXTENDS);
            if ($extends->isEmpty()) {
                continue;
            }

            $stream->token->getContentUntil($extends->get());
            $stream->token->getContentBetween($extends->get());
            $stream->token->getWhitespaceAfter();
            $stream->token->getWhitspaceBefore();
            $stream->token->getIndentation();
            $stream->token->getLineIndentation();
        }
    }

To learn more about the powerful token API, have a look at the ``AbstractToken``
base class.

The Simultaneous Token/AST Stream
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
This stream gives you simultaneous access to the token stream, and the AST stream.
Iteration is performed on the token stream, but you will have access to the
corresponding node of the AST stream as well::

    use JMS\PhpManipulator\SimultaneousTokenAstStream;

    $stream = new SimultaneousTokenAstStream();
    $stream->setInput($code);

    while ($stream->next()) {
        echo $stream->token->getContent();
        var_dump(get_class($stream->node)); // "PHPParser_Node_???"
    }

License
-------

The code is released under the business-friendly `Apache2 license`_.

Documentation is subject to the `Attribution-NonCommercial-NoDerivs 3.0 Unported
license`_.

.. _Apache2 license: http://www.apache.org/licenses/LICENSE-2.0.html
.. _Attribution-NonCommercial-NoDerivs 3.0 Unported license: http://creativecommons.org/licenses/by-nc-nd/3.0/

