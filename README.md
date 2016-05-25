A token is a promotional code for your site's premium content. You can create
thousands of tokens for a certain post or page you want to give promotional
access to. Download these tokens as CSV file, e.g. to deploy them via Mailchimp
or other mailing lists. Define exactly the timeframe of allowed access to your
post after the tokenized URL has been used.

Most importantly will get invalid instantly after the allowed amount of sessions
has expired (usually one). In case of 1 allowed session this means, that sharing
of tokenized URL's is useless. They create a cookie which keeps the door open
for the initial URL opener, but for no one else.

## Changelog

## Version 0.94

* Fix: deny import of malformed mail addresses into db.

## Version 0.93

* Bugfix: make sure mail addresses are written to db utf-8-encoded.

## Version 0.92

* Bugfix: Sanitize number ranges for sessions and "minutes until expiration" in 
  setup and edit forms of campaigns.

## version 0.91

* Bugfix: otat_counter. Enhanced DEBUG output.

## Version 0.9

* Bug fix: Redirection issue with expired access tokens solved.

## Version 0.8

* Initial release
