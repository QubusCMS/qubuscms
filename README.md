# No Longer Under Development
Qubus CMS is no longer being maintained. It has been replaced by [Devflow](https://github.com/getdevflow/cmf), a headless content management framework with CQRS and Event Sourcing.

# Qubus CMS
Qubus CMS is a developer centric, lightweight content management system (CMS) and content management framework (CMF). It is easy to install, easy to manage, and easy to extend. The majority of Qubus' codebase is derived from WordPress, however, Qubus CMS is not a fork of WordPress. It also should not be seen as a replacement for WordPress, Drupal, Joomla or any of the top used CMS's out there.

The main purpose of Qubus CMS is to give developer's an option that is geared toward how they think, how they code, and how they build websites. Although you can use Qubus CMS for your traditional CMS needs, you can also use it to build API centric applications.

Qubus CMS is pretty stable at the moment, but it is currently in beta and should no be used in production until release 1.0.

## Screenshot
![Qubus CMS Screenshot](https://tritan-cms.s3.amazonaws.com/assets/images/TriTan-Screenshot.png)

## Notable Features
* Easier installation and deployment
* Easier to migrate from one server to another
* API first
* Go headless or nearly-headless
* Caching (JSON by default when on)
* Multisite

## Requirements
* PHP 7.3+
    * gd graphics support
    * zip extention support
    * APC, XCache or Memcache(d) (optional)
* Apache or Nginx

## Installation
* Install [composer](//getcomposer.org/doc/00-intro.md) if not already installed
* Download the [latest release](//github.com/parkerj/Qubus-CMS/archive/master.zip)
* Extract the zip to the root or a subdirectory
* Copy config.sample.php to config.php
* Open config.php and edit the following constants and save the file: `TTCMS_MAINSITE` & `TTCMS_MAINSITE_PATH`. If you install on a dev server and then move it to a new server with a different domain, you will need to edit these for the new server.
* Run composer to install needed libraries: `composer install`
* Open your browser to `http://replace_url/login/` and login with the login credentials below:
    * username: qubus
    * password: qubuscms
* Visit `http://replace_url/admin/options-general/`, and change the API Key to something unique and secure.
* Create a cronjob on the server: `http://replace_url/cronjob/`. It should run every minute.

## Cookies Secret Key
- Open your config.php file, and edit the `cookies.secret.key` to something unique and secure.

## Security
Qubus CMS stores important files on the server. Whether you are on Apache/Nginx, you must make sure to secure the following directories so that files in those directories are not downloadable:

* private/cookies/*
* private/db/*
* private/sites/{site_id}/files/cache/*

Here is an example of what should go inside of the .htaccess file to secure the above directories. Make sure to change the `key` to something unique and secure.

```
# BEGIN Privatization
# This .htaccess file ensures that other people cannot download your files.

<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{QUERY_STRING} !key=replace_me
RewriteRule (.*) - [F]
</IfModule>

# END Privatization
```

## Resources
* [Learn](//learn.tritancms.com/) - TriTan documentaion.
* [Reference](//developer.qubuscms.com/) - Documentation of classes and functions.
* [Rest API](//rest.tritancms.com/) - REST API documentation.

## Libraries
As mentioned previously, Qubus CMS is also a content management framework. You can use Qubus CMS to build websites, API centric applications or both. Via composer, the following libraries become available to you to use.

| Components  | Description  |
|---|---|
| [Validation](//github.com/Respect/Validation)  | The most awesome validation engine ever created for PHP.  |
| [Zebra Pagination](//github.com/stefangabos/Zebra_Pagination)  | A generic, Twitter Bootstrap compatible, pagination library that automatically generates navigation links.  |
| [Fenom](//github.com/fenom-template/fenom)  | Lightweight and fast template engine for PHP.  |
| [Mobile Detect](//github.com/serbanghita/Mobile-Detect)  | Mobile_Detect is a lightweight PHP class for detecting mobile devices (including tablets). It uses the User-Agent string combined with specific HTTP headers to detect the mobile environment.  |
| [Graphql](//github.com/webonyx/graphql-php)  | This is a PHP implementation of the GraphQL specification based on the reference implementation in JavaScript.  |
| [RulerZ](//github.com/K-Phoen/rulerz)  | Powerful implementation of the Specification pattern in PHP.  |
| [RulerZ Specification Builder](//github.com/K-Phoen/rulerz-spec-builder)  | This library provides an object-oriented way to build Specifications for RulerZ.  |
| [Guzzle](//github.com/guzzle/guzzle)  | Guzzle is a PHP HTTP client that makes it easy to send HTTP requests and trivial to integrate with web services.  |
| [SEO Analyzer](//github.com/grgk/seo-analyzer)  | Basic PHP library to check several SEO metrics of a website.  |
| [Schema.org](//github.com/spatie/schema-org)  | A fluent builder Schema.org types and ld+json generator.  |
| [Html Menu Generator](//github.com/spatie/menu)  | The spatie/menu package provides a fluent interface to build menus of any size in your php application.  |
| [SEO Helper](//github.com/ARCANEDEV/SEO-Helper)  | SEO Helper is a package that provides tools and helpers for SEO (Search Engine Optimization).  |
| [Parsedown](//github.com/erusev/parsedown)  | Better Markdown Parser in PHP.  |
| [JoliNotif](//github.com/jolicode/JoliNotif)  | Send notifications to your desktop directly from your PHP script.  |
| [dcrypt](//github.com/mmeyer2k/dcrypt)  | A petite library of essential encryption functions for PHP 7.1+.  |
| [php-encryption](//github.com/defuse/php-encryption)  | This is a library for encrypting data with a key or password in PHP. It requires PHP 5.6 or newer and OpenSSL 1.0.1 or newer.  |
| [Parse, build and manipulate URL's](//github.com/spatie/url)  | A simple package to deal with URL's in your applications.  |
| [Throttle](//github.com/michaelesmith/Throttle)  | A basic throttling implementation to limit requests.  |
| [sitemap.xml builder](//github.com/gpslab/sitemap)  | A complex of services to build Sitemaps.xml and index of Sitemap.xml files.  |

## Theming
There is currently no theme repository due to the nature of the project. However, you can download the [Vapor](//tritan-cms.s3.amazonaws.com/themes/Vapor.zip) theme. Use this theme as an example to build your own theme.

The Liten Framework was used in the build of Qubus CMS. So, if you are interested in adding a head to your Qubus CMS install, you will need to learn about [routing](//www.litenframework.com/wiki/routing/) and [middlewares](//www.litenframework.com/wiki/middleware/).

## Plugins
Check out the [repository](https://gitlab.gitspace.us/public/) for available Qubus CMS plugins.

## Contributing
You are welcomed to contribute by tackling anything from the Todo list, sending pull requests, bug reports, etc.
