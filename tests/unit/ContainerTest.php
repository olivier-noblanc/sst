<?php
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    public function testGetReturnsSameInstance(): void
    {
        $container = new Container();
        $container->set('foo', fn() => new \stdClass());
        $a = $container->get('foo');
        $b = $container->get('foo');
        $this->assertSame($a, $b);
    }

    public function testGetThrowsOnUnknownService(): void
    {
        $container = new Container();
        $this->expectException(\RuntimeException::class);
        $container->get('nonexistent');
    }

    public function testHasReturnsTrueForRegistered(): void
    {
        $container = new Container();
        $container->set('foo', fn() => 'bar');
        $this->assertTrue($container->has('foo'));
        $this->assertFalse($container->has('baz'));
    }

    public function testFactoryReceivesContainer(): void
    {
        $container = new Container();
        $container->set('inner', fn() => 'inner_value');
        $container->set('outer', fn($c) => $c->get('inner') . '_outer');
        $this->assertEquals('inner_value_outer', $container->get('outer'));
    }
}
