# Wikibot

[![Build Status](https://travis-ci.org/Dispositif/Wikibot.svg?branch=master)](https://travis-ci.org/Dispositif/Wikibot)
![PHP from Travis config](https://img.shields.io/travis/php-v/Dispositif/Wikibot)[![Maintainability](https://api.codeclimate.com/v1/badges/b7a0aa7a832ddf24adb0/maintainability)](https://codeclimate.com/repos/5d73cea4465eac01630065a7/maintainability)
![GitHub](https://img.shields.io/github/license/Dispositif/Wikibot)
![Codecov](https://img.shields.io/codecov/c/github/Dispositif/Wikibot)[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Dispositif/Wikibot/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Dispositif/Wikibot/?branch=master)
[![StyleCI](https://github.styleci.io/repos/206988215/shield?branch=master)](https://github.styleci.io/repos/206988215)


PHP CLI app for my Wikipedia robot.

Correction and completion of bibliographic references on the french Wikipedia, using my legacy code
 and importing open data (GoogleBooks, OpenLibrary, Bibliothèque nationale de France...). 
 
Please do not play with this package. These programs can actually modify the live wiki on the net, and proper
wiki-etiquette should be followed before running it on any wiki. See https://en.wikipedia.org/wiki/WP:Bot
 
Tech stack : PHP>=7.1, RabbitMQ or MySQL, Symfony components, addwiki/mediawiki-api, etc.

List of console commands : ```php src/console list```

Special thanks to
* addshore (wiki API)
* biblys (ISBN formating)
* cloudamqp.com (AMQP server)

Memo :
 * addwiki doc http://addwiki.readthedocs.io/
