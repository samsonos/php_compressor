Compressor # SamsonPHP 

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