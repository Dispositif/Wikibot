# Wikibot

[![Build Status](https://travis-ci.org/Dispositif/Wikibot.svg?branch=master)](https://travis-ci.org/Dispositif/Wikibot)
![PHP from Travis config](https://img.shields.io/travis/php-v/Dispositif/Wikibot)[![Maintainability](https://api.codeclimate.com/v1/badges/b7a0aa7a832ddf24adb0/maintainability)](https://codeclimate.com/repos/5d73cea4465eac01630065a7/maintainability)
![GitHub](https://img.shields.io/github/license/Dispositif/Wikibot)
![Codecov](https://img.shields.io/codecov/c/github/Dispositif/Wikibot)
[![StyleCI](https://github.styleci.io/repos/206988215/shield?branch=master)](https://github.styleci.io/repos/206988215)



PHP CLI app for my wikipedian bot.

Correction and completion of bibliographic references (books, articles) on fr-Wikipedia, using my legacy
 code and importing open data (GoogleBooks, OpenLibrary...). 
 
  * See https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:Bot or https://en.wikipedia.org/wiki/WP:Bot
 
 ----
 
Technos : RabbitMQ, MySQL, Symfony Components, addwiki/mediawiki-api, etc.

List of console commands : ```php src/console list```

Special thanks to
* addshore (wiki API)
* biblys (ISBN formating)
* cloudamqp.com (AMQP server)

Memo :
 * addwiki doc http://addwiki.readthedocs.io/
