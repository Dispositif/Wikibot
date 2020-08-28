# ************************************************************
# Base de données: wikibot
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Affichage de la table page_ouvrages
# ------------------------------------------------------------

CREATE TABLE `page_ouvrages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `page` varchar(250) NOT NULL DEFAULT '',
  `raw` text,
  `opti` text,
  `opticorrected` text,
  `optidate` timestamp NULL DEFAULT NULL,
  `skip` tinyint(1) DEFAULT '0',
  `modifs` varchar(250) DEFAULT NULL,
  `version` varchar(10) DEFAULT NULL,
  `notcosmetic` int(11) DEFAULT NULL,
  `major` int(11) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `edited` timestamp NULL DEFAULT NULL,
  `priority` tinyint(4) DEFAULT '0',
  `tocorrect` tinyint(4) DEFAULT '0',
  `corrected` timestamp NULL DEFAULT NULL,
  `torevert` tinyint(4) DEFAULT '0',
  `reverted` timestamp NULL DEFAULT NULL,
  `row` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `verify` timestamp NULL DEFAULT NULL,
  `altered` int(11) DEFAULT NULL,
  `label` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `page` (`page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
