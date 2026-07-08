<?php
use PHPUnit\Framework\TestCase;

class QueryFilterBuilderTest extends TestCase
{
    public function testEmptyBuilderReturnsDefaultWhere(): void
    {
        $builder = new QueryFilterBuilder();
        $result = $builder->build();
        $this->assertEquals('1=1', $result['where']);
        $this->assertEmpty($result['params']);
    }

    public function testAddEqualAddsCondition(): void
    {
        $builder = new QueryFilterBuilder();
        $builder->addEqual('r.type', 'rsst');
        $result = $builder->build();
        $this->assertStringContainsString('r.type', $result['where']);
        $this->assertCount(1, $result['params']);
    }

    public function testAddEqualSkipsNullValue(): void
    {
        $builder = new QueryFilterBuilder();
        $builder->addEqual('r.type', null);
        $result = $builder->build();
        $this->assertEquals('1=1', $result['where']);
    }

    public function testAddEqualSkipsEmptyString(): void
    {
        $builder = new QueryFilterBuilder();
        $builder->addEqual('r.etat', '');
        $result = $builder->build();
        $this->assertEquals('1=1', $result['where']);
    }

    public function testAddEqualSkipsZero(): void
    {
        $builder = new QueryFilterBuilder();
        $builder->addEqual('r.site_id', 0);
        $result = $builder->build();
        $this->assertEquals('1=1', $result['where']);
    }

    public function testAddInAddsInClause(): void
    {
        $builder = new QueryFilterBuilder();
        $builder->addIn('r.etat', ['nouveau', 'en_cours']);
        $result = $builder->build();
        $this->assertStringContainsString('IN', $result['where']);
        $this->assertCount(2, $result['params']);
    }

    public function testAddInSkipsEmptyArray(): void
    {
        $builder = new QueryFilterBuilder();
        $builder->addIn('r.etat', []);
        $result = $builder->build();
        $this->assertEquals('1=1', $result['where']);
    }

    public function testMultipleConditionsAreAndComposed(): void
    {
        $builder = new QueryFilterBuilder();
        $builder->addEqual('r.type', 'rsst');
        $builder->addEqual('r.etat', 'nouveau');
        $result = $builder->build();
        $this->assertStringContainsString('AND', $result['where']);
        $this->assertCount(2, $result['params']);
    }
}
