<?php

use PHPUnit\Framework\TestCase as BaseTestCase;

require_once __DIR__ . '/../vendor/autoload.php';

error_reporting(-1);

class TestCase extends BaseTestCase
{
    protected function expectCallableOnce()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableNever()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnceWith($value)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->withConsecutive(func_get_args());

        return $mock;
    }

    /**
     * @link https://github.com/reactphp/react/blob/master/tests/React/Tests/Socket/TestCase.php (taken from reactphp/react)
     */
    protected function createCallableMock()
    {
        return $this->getMockBuilder('CallableStub')->getMock();
    }

    protected function expectPromiseResolve($promise)
    {
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $promise->then($this->expectCallableOnce(), $this->expectCallableNever());

        return $promise;
    }

    protected function expectPromiseReject($promise)
    {
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());

        return $promise;
    }
}

class CallableStub
{
    public function __invoke()
    {
    }
}
