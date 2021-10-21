# plite
PHP Lite Framework

Adds some basic libraries for working with console-viewed logging, PHP as a CLI tool, handling basic server-side web API requests, a very small Postgres abstraction, as well as a small framework for abstracting a few AWS services (S3, SecretsManager, and SES) and Twilio/Sinch/Plivo.  Also comes with tools to help with ETL.

The AWS abstraction classes also allow you to use a local, offline version for test and dev, which does NOT require an AWS account or access to its services, but through a series of small config changes, can allow your code to immediately use cloud resources (e.g., AWS S3 instead of your local filesystem).

## Entry Points

There are several entry points, depending on what you're using this for.
