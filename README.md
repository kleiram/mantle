# Mantle

Mantle makes it easy to write a simple model layer for your PHP applications.

## Installation

Via Composer:

```json
{
    "require": {
        "mantle/mantle": "~1.0"
    }
}
```

## Usage

What's wrong with the way models are usually written in PHP?

Let's use the GitHub API as an example. How would one usually represent a
[GitHub issue](https://developer.github.com/v3/issues/#get-a-single-issue) in PHP?

```php
<?php
class Issue
{
    const STATE_CLOSED  = 0;
    const STATE_OPEN    = 1;

    /** @var string */
    public $url;

    /** @var string */
    public $htmlUrl;

    /** @var integer */
    public $number;

    /** @var integer */
    public $state;

    /** @var string */
    public $reporterLogin;

    /** @var User */
    public $assignee;

    /** @var DateTime */
    public $updatedAt;

    /** @var string */
    public $title;

    /** @var string */
    public $body;
}
```

Seems perfectly fine! Now, what happens when we want to map a JSON response from
the GitHub API to this model? We end up with a _lot_ of mapping code:

```php
<?php
$json = ...; // Get response from GitHub API

$assignee   = new User();
$issue      = new Issue();

$issue->url             = $json->url;
$issue->htmlUrl         = $json->html_url;
$issue->number          = $json->number;
$issue->updatedAt       = new \DateTime($json->updated_at);

$issue->title           = $json->title;
$issue->body            = $json->body;

$issue->reporterLogin   = $json->user->login;
$issue->assignee        = $assignee;

$issue->state           = $json->state == 'open' ? Issue::STATE_OPEN : Issue::STATE_CLOSED;

// And even more code to parse the assignee property!
```

Whoaw, that is a lot of boilerplate code for something that is so common! And
even now there are still issues:

 - If the `url` or `html_url` fields are missing in the response, an error will
   be thrown;
 - There is no way to update a `Issue` with new data from the server;

 ## Insert Mantle

This is where Mantle comes in! Using Mantle, the above code can be changed into:

```php
use Mantle\Mantle;

$json = ...; // Get response from GitHub API

$issue  = Mantle::transform($json, 'Issue');
```

It's that easy! Mantle will try and build the object by looking at properties in
the class that you specifiy (it should be the `FQCN`) and it will even try to
convert `camelCase` names to `snake_case`!

### Property mapping

The only thing it will not do (and that's a good thing, since it won't know what
to do) is convert nested properties (such as the `reporterLogin` property).

Luckily, you won't have to do that by hand after the `Issue` object is created!
Mantle will try and look for a `getPropertyMapping` method in your `Model`
class, and if it exists, use that mapping over the one it creates by itself.
That means that our `Issue` model has to be extended a little bit:

```php
<?php
class Issue
{
    // Properties

    public function getPropertyMapping()
    {
        return array(
            'reporterLogin' => 'user.login'
        );
    }
}
```

Mantle expects that an array is returned from the `getPropertyMapping` method
in which the keys of the array are the properties of the model and the
values are JSON key-paths. If you don't implement this method, Mantle will
still work, it just won't handle nested values.

If there are some properties that exist both in the model and the JSON object
that you (for some reason) don't want mapped, you can set a `null` value as
the value for the property:

```php
<?php
return array(
    'unmapped-property' => null
);
```

### Transformers

If we look at the `Issue` model again, there are some things that might raise
questions: the `updatedAt`, `assignee` and `state` properties.

You can let Mantle handle this too by specifying _transformers_. A transformer
is a method that transforms an input value to another output value.

Transforming these properties is really easy. All you have to do is create some
extra methods in your model. For the `updatedAt` and `state` properties, they
might look something like this:

```php
public function updatedAtTransformer($updatedAt)
{
    return new \DateTime($updatedAt);
}

public function stateTransformer($state)
{
    return $state == 'open' ? static::STATE_OPEN : static::STATE_CLOSED;
}
```

Mantle expects transformers to have the name `[property]Transformer`.

This leaves us with only one thing left: the `assignee` property. As usual,
you'll only have to define one extra (really simple) method to transform
this property:

```php
public function assigneeClass()
{
    return 'User';
}
```

(It's assumed that a `User` class exists).

Mantle expects that you return the `FQCN` of the class for the class that you
want the JSON object to be transformed into. It can even handle arrays for you!
Let's say that you have a JSON response like so (not related to the GitHub API):

```json
{
    "username": "bob",
    "tickets": [
        {
            "title": "Foo",
            "body": "Lorem ipsum dolor sit amet"
        },
        {
            "title": "Bar",
            "body": "Lorem ipsum dolor sit amet"
        },
        {
            "title": "Baz",
            "body": "Lorem ipsum dolor sit amet"
        }
    ]
}
```

You can then (in a fictive `User` model) implement the `ticketsClass` method
that returns (for example) `Vendor\Project\Model\Ticket`. Mantle will transform
the `tickets` field in the JSON response into an array of `Ticket` models for
you!

### Existing objects

It happens (sometimes) that you want to update an existing object with new data
from the server. Well, Mantle handles that too! Instead of passing class name
as the second argument for Mantle you can pass an existing object:

```php
<?php
use Mantle\Mantle;

$data = ...; // Fetch user data from somewhere

$user   = Mantle::transform($data, 'User');

// A lot of stuff happens, maybe some time passes

$data = ...; // Fetch fresh user data from somewhere
$user = Mantle::transform($data, $user);
```

The only requirement is that the `$data` passed is actually an `stdClass` object
and not an `array` (an array wouldn't make sense in this case since you're only
transforming a single object).

### Data source

There's no requirement for what kind of data source you have to use. You can
fetch data from a remote server or a local file or even create a `stdClass`
object in the application itself and map it using Mantle!

### Callbacks

An extra functionality in Mantle is the possibility to specify a callback that's
called when the transformation of an object is complete. This way, it's possible
to perform extra operations on each object in an array or a specific object
without having to do extra work after the transformation. Basically, it changes
the following piece of code:

```php
<?php
use Mantle\Mantle;

$data = ...; // A list of users fetched from somewhere
$group = new Vendor\Project\Group();

$users = Mantle::transform($data, 'Vendor\Project\User');

foreach ($users as $user) {
    $user->setGroup($group);
}
```

To this:

```php
use Mantle\Mantle;

$data = ...; // A list of users fetched from somewhere
$group = new Vendor\Project\Group();

$users = Mantle::transform($data, 'Vendor\Project\User', function ($user) use ($group) {
    $user->setGroup($group);
});
```

It's, of course, totally up to you whether you want to use a closure, the name
of a function or even a class method!

## Testing

Mantle is fully unit tested. The tests can be run with PHPUnit:

```shell
$ phpunit
```

## Attributions

For those of you who are familiar with Objective-C, this library will look familiar. This
is because it's based on the Objective-C library [Mantle](https://github.com/Mantle/Mantle)
(that's why it's named the same way).

## Contributing

Please see [CONTRIBUTING](https://github.com/kleiram/mantle/blob/master/CONTRIBUTING.md)
for details.

## Credits

 - [Ramon Kleiss](https://github.com/kleiram)
 - [All Contributors](https://github.com/kleiram/mantle/contributors)

## License

The MIT License (MIT). Please see [License File](https://github.com/kleiram/mantle/blob/master/LICENSE)
for more information.
