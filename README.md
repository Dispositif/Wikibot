# Wikibot

[![Build Status](https://scrutinizer-ci.com/g/Dispositif/Wikibot/badges/build.png?b=master)](https://scrutinizer-ci.com/g/Dispositif/Wikibot/build-status/master)
[![Maintainability](https://api.codeclimate.com/v1/badges/b7a0aa7a832ddf24adb0/maintainability)](https://codeclimate.com/repos/5d73cea4465eac01630065a7/maintainability)
![GitHub](https://img.shields.io/github/license/Dispositif/Wikibot)
[![Code Coverage](https://scrutinizer-ci.com/g/Dispositif/Wikibot/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Dispositif/Wikibot/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Dispositif/Wikibot/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Dispositif/Wikibot/?branch=master)


PHP CLI app for Wikipedia robot. 

See https://fr.wikipedia.org/wiki/Utilisateur:CodexBot for the live bot.

- Correction and completion of bibliographic references on the french Wikipedia, using my legacy code
 and importing open data (GoogleBooks, OpenLibrary, Bibliothèque nationale de France, Wikidata...) based on ISBN or 
other book's identifiers.
Lots of data cleaning and post-processing, because bibliographic data is sooo serious but inconsistent.

- Completion of "external links" from the World Wide Web : the bot acts like a web crawler and transforms the 
raw links (http://...) into detailed references with page's title, author, site name, date, etc. It uses metadata from 
OpenGraph, JSON-LD, DublinCore, TwitterCard or naive prediction from HTML. Not a lot of data postprocessing, because web
data is cool but rather consistent (SEO). It manages also dead links (404, DNS, etc.) and redirects.

Please do not play with this package. These programs can actually modify the live Wikipedias, and proper
wiki-etiquette should be followed before running it on any wiki. See https://en.wikipedia.org/wiki/WP:Bot for rules and
authorization requests.
 
Tech stack : PHP >=8.1 (version 1.1 on PHP7.2), RabbitMQ or MySQL, composer libraries (Symfony components, addwiki/mediawiki-api, etc), 
hexagonal architecture….

Use ``make`` on root, for the list of some available commands.

<img src="https://raw.githubusercontent.com/Dispositif/Wikibot/master/docs/workers.png" alt="schemas of workers" style="max-width:300px;" />

Special thanks to
* addshore (wiki API)
* biblys (ISBN formating)
* cloudamqp.com (AMQP server)
* many frwiki users for quality control

Memo :
 * addwiki doc http://addwiki.readthedocs.io/
