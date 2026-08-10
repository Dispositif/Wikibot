<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Infrastructure\ServiceFactory;

require_once __DIR__ . '/../myBootstrap.php';

/**
 * One-shot import of the pre-BotEditJournalInterface flat files into bot_page_analyzed
 * (article_edited.txt, article_externRef_edited.txt, gooBot_edited.txt — see
 * WorkerAnalyzedTitlesTrait / BotEditJournalInterface). Run manually, once, against a
 * DB that already has migration 0001 applied (`make run service=db-migrate`).
 *
 * Safe to re-run : recordAnalyzed() is INSERT IGNORE on the (page, task) primary key.
 * The files themselves have no per-line timestamp, so every imported row gets
 * analyzed_at = now() — that's a known loss of precision, not a bug.
 */

if (!ServiceFactory::BOT_EDIT_JOURNAL_ENABLED) {
    echo "ServiceFactory::BOT_EDIT_JOURNAL_ENABLED is false — flip it to true first "
        . "(see its docblock), otherwise this import has nothing to write to.\n";
    exit(1);
}

$files = [
    'extern-ref' => __DIR__ . '/../resources/article_externRef_edited.txt',
    'goo-extern' => __DIR__ . '/../resources/gooBot_edited.txt',
    'default' => __DIR__ . '/../resources/article_edited.txt',
];

foreach ($files as $task => $file) {
    $titles = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($titles === false) {
        echo "  [skip] $task : $file not found\n";
        continue;
    }

    $journal = ServiceFactory::getBotEditJournal($file);
    $count = 0;
    foreach ($titles as $title) {
        $journal->recordAnalyzed($title, $task);
        $count++;
        if ($count % 5000 === 0) {
            echo "    ... $count titles imported for $task\n";
        }
    }
    echo "  [done] $task : $count titles imported from $file\n";
}

echo "*** Import complete ***\n";
