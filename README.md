# plite
PHP Lite Framework

Adds some basic libraries for working with console-viewed logging, PHP as a CLI tool, handling basic server-side web API requests, a very small Postgres abstraction, as well as a small framework for abstracting a few AWS services (S3, SecretsManager, and SES) and Twilio/Sinch/Plivo.  Also comes with tools to help with ETL.

The AWS abstraction classes also allow you to use a local, offline version for test and dev, which does NOT require an AWS account or access to its services, but through a series of small config changes, can allow your code to immediately use cloud resources (e.g., AWS S3 instead of your local filesystem).

There are several entry points for using this library, depending on the intended usage.

## Entry Point: CLI

If you are using this to create a command-line script (e.g., executable with a shebang), then your starting point is the class `vertwo\plite\CLI`.

Just extend `CLI`, and implement three methods:

* `main()`
* `getShortOpts()`
* `getLongOpts()`

The latter two methods are for unix-style options-handling can return empty string and an empty array.  So, for a hello-world, just this will do:

```
class HelloWorld extends CLI
{
    protected function getShortOpts () { return ""; }
    protected function getLongOpts () { return []; }


    /**
     * CLI entry point.
     *
     * @return int
     */
    public function main ()
    {
        echo "Hello, world!\n";
        return 0;
    }
}


HelloWorld::run();
```

And, if you add a shebang, a PHP tag, a `use` statement, and a `require` statement, and then make the script executable, you'll be able to just execute this php file on the CLI (and, obviously, making sure to chmod +x or whatever your OS needs to make things runnable).

Now, obviously, that's a LOT of lines of code for a file that could have just been this:

```
#! /usr/bin/php
<?php
echo "Hello, world!\n";
```

So, why put it up with it?

Well, just 1 reason: options-handling.

In order for PHP to function well in a unix-y shell-environment, scripts often nee to be run with different arguments to have slightly different behavior.

Suppose our PHP script is named `hello`.

Let's say we want to add a *verbose* switch to our program, so that we can call it like this:

`$ hello -v`

Then, we just change one line:

```
    protected function getShortOpts () { return "v"; }
```

And we can use it like this:

```
    public function main ()
    {
        if ( $this->hasopt("v") ) echo "About to print string!\n";
        echo "Hello, world!\n";
        return 0;
    }
```

This makes CLI PHP much more effective.

And suppose we wanted a long option: `--name your_name`

Then, we change the implementation of `getLongOpts()` like this:

```
    protected function getLongOpts ()
    {
        return [
            self::REQ("name"),
        ];
    }
```

and use it like this:

```
    public function main ()
    {
        if ( $this->hasopt("v") ) echo "About to print string!";

        if ( $this->hasopt("name") )
        {
            echo "Hello, world, " . $this->getopt("name") . "!\n";
        }
        else
        {
            echo "Hello, world!\n";
        }
        return 0;
    }
```

It's beautiful.

Here's the final, (potentially) executable script:

```
#! /usr/bin/php
<?php



use vertwo\plite\CLI;



require_once __DIR__ . "/../vendor/autoload.php";



class HelloWorld extends CLI
{
    protected function getShortOpts () { return "v"; }

    protected function getLongOpts ()
    {
        return [
            self::REQ("name"),
        ];
    }


    /**
     * CLI entry point.
     *
     * @return int
     */
    public function main ()
    {
        if ( $this->hasopt("v") ) echo "About to print string!\n";

        if ( $this->hasopt("name") )
        {
            echo "Hello, world, " . $this->getopt("name") . "!\n";
        }
        else
        {
            echo "Hello, world!\n";
        }
        return 0;
    }
}



HelloWorld::run();
```
