#!/usr/bin/env bash
# Backup chiffré des fichiers privés Wikibot (code non public + .env + data perso).
# Pas de git, pas d'historique : juste une archive .tar.gz.enc horodatée,
# à copier ensuite manuellement sur un disque externe / cloud privé.
#
# Usage    : ./bin/backup-private.sh
# Restaurer: openssl enc -d -aes-256-cbc -pbkdf2 -in ARCHIVE.tar.gz.enc -out ARCHIVE.tar.gz && tar -xzf ARCHIVE.tar.gz

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST_DIR="$HOME/Work/Work-private-backups"
STAMP="$(date +%Y%m%d-%H%M%S)"
ARCHIVE="wikibot-private-$STAMP.tar.gz"

PATHS=(
  src/Application/Bazar
  src/Domain/Bazar
  src/Domain/TALN
  src/Application/Web
  src/Infrastructure/unused
  src/Application/CLI/cron_electionAdmin.php
  src/Application/CLI/userTrack.php
  src/Application/CLI/tor.php
  src/Application/resources/botTalk_config.json
  src/Application/resources/phrases_zizibot.txt
  src/Application/resources/phrases_haiku.wiki
  src/Application/resources/phrases_voteAdmin_pour.txt
  src/Application/resources/polarity-PDDpour.txt
  src/Application/resources/polarity-PDDcontre.txt
  .env
  .env.codexbot2
  .env.ironie
  .env.zizibot
  .env.test
)

mkdir -p "$DEST_DIR"
cd "$REPO_ROOT"

EXISTING=()
for p in "${PATHS[@]}"; do
  if [ -e "$p" ]; then
    EXISTING+=("$p")
  else
    echo "(absent, ignoré : $p)"
  fi
done

tar -czf "$DEST_DIR/$ARCHIVE" "${EXISTING[@]}"

echo ""
echo "Choisis un mot de passe pour chiffrer l'archive (à retenir / mettre dans ton gestionnaire de mots de passe) :"
openssl enc -aes-256-cbc -pbkdf2 -salt -in "$DEST_DIR/$ARCHIVE" -out "$DEST_DIR/$ARCHIVE.enc"
rm "$DEST_DIR/$ARCHIVE"

echo ""
echo "Backup chiffré créé : $DEST_DIR/$ARCHIVE.enc"
echo "Pense à le copier sur un disque externe ou un cloud privé, puis à le supprimer d'ici si besoin."
