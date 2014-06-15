<?php
namespace League\Mantle\Test;

use League\Mantle\Mantle;

class MantleTest extends \PHPUnit_Framework_TestCase
{
    public function testThrowsExceptionOnInvalidArgument()
    {
        $this->setExpectedException('InvalidArgumentException');

        $mantle = new Mantle();
        $mantle->transform('foo', null);
    }

    public function testTransformJsonArrayIntoArrayOfObjects()
    {
        $data = json_decode(file_get_contents(__DIR__.'/../../../data/users.json'));

        $mantle  = new Mantle();
        $objects = $mantle->transform($data, 'League\Mantle\Stub\User');

        $this->assertCount(2, $objects);
        $this->assertInstanceOf('League\Mantle\Stub\User', $objects[0]);
        $this->assertInstanceOf('League\Mantle\Stub\User', $objects[1]);
    }

    public function testTransformJsonObjectIntoObject()
    {
        $data = json_decode(file_get_contents(__DIR__.'/../../../data/user.json'));

        $mantle = new Mantle();
        $user = $mantle->transform($data, 'League\Mantle\Stub\User');

        $this->assertInstanceOf('League\Mantle\Stub\User', $user);
        $this->assertEquals('alice', $user->username);
        $this->assertEquals('alice@example.org', $user->email);
        $this->assertEquals('http://example.org/users/alice/profile', $user->profileUrl);
        $this->assertEquals(new \DateTime('Tue Aug 28 21:16:23 +0000 2012'), $user->registeredAt);
        $this->assertInstanceOf('League\Mantle\Stub\Profile', $user->profile);
        $this->assertEquals('Alice', $user->profile->firstName);
        $this->assertEquals('Doe', $user->profile->lastName);
        $this->assertEquals('127.0.0.1', $user->profile->location);
    }
}
