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

namespace JMS\PhpManipulator\PhpParser;

/**
 * Wrapper node for multiple statements.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class BlockNode extends \PHPParser_NodeAbstract implements \Countable, \ArrayAccess
{
    public function count()
    {
        return count($this->subNodes);
    }

    public function offsetExists($offset)
    {
        return isset($this->subNodes[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->subNodes[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->subNodes[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->subNodes[$offset]);
    }
}