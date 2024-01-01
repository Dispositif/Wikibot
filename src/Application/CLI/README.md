Description des scripts :


* externRefProcess [article] : conversion URL => {article} ou {lien web} d'après requête CirrusSearch
* lastExternRefProcess : conversion URL => {article}/{lien web} d'après les Recents Changes
* az-externRefProcess [article] : conversion URL => {article} ou {lien web} d'après liste des articles WP
* testExternlink <http://...> : pour tester en console la complétion d'une URL

* gooExternProcess : convertit les liens externes Google Books en {ouvrage}

Remplissage des citations {ouvrage} à compléter :
* 1. wikiScanProcess [article] : ajout de nouvelles citations {ouvrage} en BDD
* 1 bis cron_scanLabel : scan des potentiels BA/AQD et nouveaux BA/ADQ pour complétion {ouvrage} et {lien web}
* 2. ouvrageCompleteProcess : analyse/complétion des {ouvrage} en BDD (max 2000/jour)
* 3. ouvrageEditProcess : édition sur WP des améliorations {ouvrage}

* botstats : affichage CLI des statistiques
* cleanErrorReport : supprime les messages de signalement d'erreur en PD d'article si les erreurs ont été corrigées
* Monitor : monitoring des edits humains après édition du bot

Pas importants : 

* avancement2 : mets à jour les tâches bot en cours
* globalProcess : Monitoring des edits humains + trucs
* notificationCodex : gestion des notifications (+ lancement externRefProcess ou {ouvrage)
* plumeBot : bot de remplacement (style pywikipedia)

