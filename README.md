# social-digest #

Distributed digest system.

## Features ##

* File or database configuration
* News' sections using feed sources (RSS)
* A common agenda supporting iCalendar (.ics) and Google Calendar API
* Recent tweets using Twitter API -currently deprecated-
* Short urls using Bitly API
* Filters such as max results, interval time or custom tags
* Custom CSS style (inline or file)
* Mail delivery

## How it works? ##

There are two available commands (`run` and `mail`) that require a previous configuration to work:

* `app.yml` file: details, sections, filters, apis, mail server, translations, database connection, etc
* `feeds.yml` file or `database` config: feeds list (by section)
* `calendars.yml` file or `database` config: calendars list

The app searches for configuration files at `/conf` folder by default. This path could be overridden setting a custom one, as described below at commands section, and is possible to store `feeds` / `calendars` lists in a database instead of using files. See `db` configuration at `app.yml` example file for more details.

There is also an option to read feeds / calendars sources from a database. It requires to set up server connection and table columns configuration.

The initial example files, at `/conf` folder, are a good start to see how to setup a custom app.

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
