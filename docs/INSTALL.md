## Install et scripts actuels

Installation
* PHP>=7.2
* > git clone https://github.com/Dispositif/Wikibot.git  .
* > composer install
* installer database MySQL/Maria d'après resources/wikibot_schema.sql
* completer la config .env.dist et renommer en .env
* lancer tests PHPUnit sur la suite de tests d'intégration (BnF,Google,WikiData,Wikipedia...)

Scripts dans Application/Examples :
* wstat_titles : récupère liste d'articles sur wstat.fr sinon créer un .txt
* ArticleScan : scanner les articles WP d'une liste .txt et remplit la BDD avec les citations extraites (colonne
 "raw")
* CompleteProcess : pour corriger/compléter les citations (colonne "opti").
* EditProcess.php : pour éditer Wikipedia avec les citations corrigées
* Monitor : worker qui analyse les corrections humaines suite au passage du bot
* botstats : affiche stats + mise à jour de la page de surveillance sur Wikipédia (suite à Monitor)
* cleanErrorReport : supprime les signalements d'erreur en page discussion des articles si les erreurs ont été corrigées.
* avancement : mise à jour statistiques avancement (%, nbre citations analysées)
* WikiDocument : mise à jour sur Wikipédia de la page fonctionnalités.txt
* plumeBot : script de remplacement simple (regex) pour correction bug ou WP:RBOT
