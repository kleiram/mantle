<?php
namespace Mantle\Stub;

class User
{
    public $age;
    public $email;
    public $username;
    public $profileUrl;
    public $registeredAt;
    public $friends;
    public $profile;

    public function getPropertyMapping()
    {
        return array(
            'profileUrl' => '_links.profile.href',
            'friends' => null,
        );
    }

    public function registeredAtTransformer($registeredAt)
    {
        return new \DateTime($registeredAt);
    }

    public function profileClass()
    {
        return 'Mantle\Stub\Profile';
    }
}
