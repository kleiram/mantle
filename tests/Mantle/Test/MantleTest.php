<?php
namespace Mantle\Test;

use Mantle\Mantle;
use Mantle\Stub\User;
use Mantle\Stub\Profile;

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
        $data = json_decode(file_get_contents(__DIR__.'/../../data/users.json'));

        $mantle  = new Mantle();
        $objects = $mantle->transform($data, 'Mantle\Stub\User');

        $this->assertCount(2, $objects);
        $this->assertInstanceOf('Mantle\Stub\User', $objects[0]);
        $this->assertInstanceOf('Mantle\Stub\User', $objects[1]);
    }

    public function testTransformJsonObjectIntoObject()
    {
        $data = json_decode(file_get_contents(__DIR__.'/../../data/user.json'));

        $mantle = new Mantle();
        $user = $mantle->transform($data, 'Mantle\Stub\User');

        $this->assertInstanceOf('Mantle\Stub\User', $user);
        $this->assertEquals('alice', $user->username);
        $this->assertEquals('alice@example.org', $user->email);
        $this->assertEquals('http://example.org/users/alice/profile', $user->profileUrl);
        $this->assertEquals(new \DateTime('Tue Aug 28 21:16:23 +0000 2012'), $user->registeredAt);
        $this->assertInstanceOf('Mantle\Stub\Profile', $user->profile);
        $this->assertEquals('Alice', $user->profile->firstName);
        $this->assertEquals('Doe', $user->profile->lastName);
        $this->assertEquals('127.0.0.1', $user->profile->location);
    }

    public function testTransformJsonObjectIntoExistingObject()
    {
        $data = json_decode(file_get_contents(__DIR__.'/../../data/user.json'));
        $user = new User();

        $user->age = 33;
        $user->email = 'john@example.org';
        $user->username = 'john';
        $user->profileUrl = 'http://example.org/users/john/profile';
        $user->registeredAt = new \DateTime();
        $user->friends = array();

        $user->profile = new Profile();
        $user->profile->firstName = 'John';
        $user->profile->lastName = 'Doe';
        $user->profile->location = 'Amsterdam';

        $mantle = new Mantle();
        $user = $mantle->transform($data, $user);

        $this->assertInstanceOf('Mantle\Stub\User', $user);
        $this->assertEquals('alice', $user->username);
        $this->assertEquals('alice@example.org', $user->email);
        $this->assertEquals('http://example.org/users/alice/profile', $user->profileUrl);
        $this->assertEquals(new \DateTime('Tue Aug 28 21:16:23 +0000 2012'), $user->registeredAt);
        $this->assertInstanceOf('Mantle\Stub\Profile', $user->profile);
        $this->assertEquals('Alice', $user->profile->firstName);
        $this->assertEquals('Doe', $user->profile->lastName);
        $this->assertEquals('127.0.0.1', $user->profile->location);
    }

    public function testThrowsExceptionOnNonStringWhenTransformingJsonArray()
    {
        $this->setExpectedException('InvalidArgumentException');

        $data = array(
            (object) array(
                'username' => 'foo'
            )
        );

        $mantle = new Mantle();
        $mantle->transform($data, $data);
    }

    public function testCallsCallbackWhenTransformingObject()
    {
        $data = json_decode(file_get_contents(__DIR__.'/../../data/user.json'));

        $mantle = new Mantle();
        $user = $mantle->transform($data, 'Mantle\Stub\User', function ($user) {
            $user->username = 'user_'. $user->username;
        });

        $this->assertEquals('user_alice', $user->username);
    }

    public function testCallsCallbackWhenTransformingArray()
    {
        $data = json_decode(file_get_contents(__DIR__.'/../../data/users.json'));

        $mantle = new Mantle();
        $users = $mantle->transform($data, 'Mantle\Stub\User', function ($user) {
            $user->username = 'user_'. $user->username;
        });

        $this->assertEquals('user_alice', $users[0]->username);
        $this->assertEquals('user_bob', $users[1]->username);
    }
}
