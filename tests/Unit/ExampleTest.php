<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testExample(): void
    {
        $this->assertTrue(true);
    }

    public function testAddition(): void
    {
        $result = 2 + 2;
        $this->assertEquals(4, $result);
    }

    public function testString(): void
    {
        $string = 'Hello World';
        $this->assertStringContainsString('World', $string);
    }
}
