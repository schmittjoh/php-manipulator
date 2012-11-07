<?php

class Foo {
    public function staticCall()
    {
        self::$unsupported;
        self::foo();

        throw new ParseException(sprintf('The pseudo-class %s is not supported', $this->name));
    }
}