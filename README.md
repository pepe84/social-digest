# social-digest #

Distributed digest system.

## Features ##

* News sections using feed sources (RSS)
* A common agenda using Google Calendar API
* Recent tweets using Twitter API
* Short urls using Bitly API
* Filters such as max results, interval time or custom tags
* Custom CSS style (inline or file)
* Mail delivery

## How it works? ##

There are two available commands (`run` and `mail`) that require three configuration files:

* `app.yml`: info, sections, filters, apis, mail server, translations
* `blogs.yml`: feeds list (by section)
* `calendars.yml`: calendars list

Those files could be inside the default configuration folder or in a custom one (see commands section). The initial default files explain how to setup the app (see file comments).

## Commands ##

### run ###

builds an HTML digest using the given configuration and optionally sends it by mail

    social-digest.php run [config]

Parameter `[config]` is optional and it's useful to set a custom configuration folder path. Leave this parameter empty or use "default" value is the same, in both cases the app will use /conf folder.

### mail ###

send HTML digest by mail

    social-digest.php mail [config] [address1] .. [addressN]

Parameter `[config]` is similar to run command one and parameters `[address1] .. [addressN]` have a structure "header:mail1,..,mailN" where "header" could be "from", "to", "cc" or "bcc". Full example: `from:mail1 to:mail2,mail3 cc:mail4 bcc:mail5,mail6`.

## Tips ##

After executing `run` command you could send the result to many adresses as you want using the `mail` command. This is very useful when you setup a cronjob for example:

    social-digest.php run;
    social-digest.php mail default to:u1@s1.me;
    social-digest.php mail default to:u2@s2.me cc:u3@s2.me