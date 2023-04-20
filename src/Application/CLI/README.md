Description des scripts :


* externRefProcess [article] : conversion URL => {article} ou {lien web}
* testExternlink <http://...> : pour tester une URL

* 1. wikiScanProcess [article] : ajout de nouvelles citations {ouvrage} en BDD
* 2. ouvrageCompleteProcess : analyse des {ouvrage} en BDD (max 2000/jour)
* 3. ouvrageEditProcess : edition sur WP des améliorations {ouvrage}

* botstats : affichage CLI des statistiques
* cleanErrorReport : supprime les messages de signalement d'erreur en PD d'article si les erreurs ont été corrigées
* Monitor : monitoring des edits humains après édition du bot

Pas importants : 

* avancement : mets à jour l'avancement de la task {ouvrage} (52%)
* globalProcess : Monitoring des edits humains + trucs
* notificationCodex : gestion des notifications (+ lancement externRefProcess ou {ouvrage)
* plumeBot : bot de remplacement (style pywikipedia)

