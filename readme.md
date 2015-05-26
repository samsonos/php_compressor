# SamsonPHP Compressor

[![Latest Stable Version](https://poser.pugx.org/samsonos/php_compressor/v/stable.svg)](https://packagist.org/packages/samsonos/php_compressor) 
[![Build Status](https://travis-ci.org/samsonos/php_compressor.png)](https://travis-ci.org/samsonos/php_compressor) 
[![Code Coverage](https://scrutinizer-ci.com/g/samsonos/php_compressor/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/samsonos/php_compressor/?branch=master)
[![Code Climate](https://codeclimate.com/github/samsonos/php_compressor/badges/gpa.svg)](https://codeclimate.com/github/samsonos/php_compressor) 
[![Total Downloads](https://poser.pugx.org/samsonos/php_compressor/downloads.svg)](https://packagist.org/packages/samsonos/php_compressor)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/samsonos/php_compressor/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/samsonos/php_compressor/?branch=master)
[![Stories in Ready](https://badge.waffle.io/samsonos/php_compressor.png?label=*&title=Ready)](https://waffle.io/samsonos/php_compressor)

Exclusive module which differs this PHP framework among all others,

Introduction

SamsonPHP is designed to be very extensible and uses DRY principle as it's core idea
so you don't have to download each module in your every project(web-application), you
can just save one copy of each module in your file system and set path to it in index.php 
 
Main goal

The introductory part perfectly fits for development environment but what to do whith production
when you need to upload your project to outer server? As usual, in all modern PHP frameworks you must
copy all the files, from all the modules used by your project into one folder and download it to server,
but wait, SamsonPHP was not designed for this kind of things! It was designed for you - for web-developer!

You just connect Comressor module to your project in index.php and enter /compressor url from your browser
and that's it, you got automaticly minified, combined, optimized version of your web-application at few seconds.

Compressor automaticly supports PHP 5.2 for old corporate web serververs, dispites namespaces, required class order,
it analyzes and generates perfect PHP code, plus it uses optimisation for speed imporvements such as:
- saving core snapshot, no more loading for each request
- removing unnesesary code from core and modules using special comments //[PHPCOMPRESSOR(remove,start)], //[PHPCOMPRESSOR(remove,end)]
- preprocessing templates for speed
- minifying views and saving them as variables using <<EOT approach
- copying all images, docs, and all other resources preserving module structure

Compressor generates one php file - index.php, in the end you get the posiible variant of your web-application for production

egorov@samsonos.com
