<?php

class MetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testGetMetadataWithComplexHierarchy()
    {
        switch ($class->name) {
            case 'Metadata\Tests\Fixtures\ComplexHierarchy\SubClassB':
                new PropertyMetadata($class->name, 'baz');
                break;
        }
    }
}