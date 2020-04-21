<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Application;

use App\Infrastructure\ServiceFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

trait TalkPageEditTrait
{
    /**
     * todo extract to class ?
     *
     * @param array                $rows Collection of citations
     * @param LoggerInterface|null $log
     *
     * @return bool
     */
    private function sendOuvrageErrorsOnTalkPage(array $rows, LoggerInterface $log = null): bool
    {
        if ($log === null) {
            $log = new NullLogger();
        }
        if (empty($rows[0]) || empty($rows[0]['page'])) {
            return false;
        }
        $mainTitle = $rows[0]['page'];
        $log->notice("** Send Error Message on talk page. Wait 3...");
        sleep(3);

        // format wiki message
        $errorList = '';
        foreach ($this->errorWarning[$mainTitle] as $error) {
            $errorList .= sprintf("* <span style=\"background:#FCDFE8\"><nowiki>%s</nowiki></span> \n", $error);
        }

        $diffStr = '';
        try {
            // get last bot revision ID
            $main = ServiceFactory::wikiPageAction($mainTitle, true);
            if (getenv('BOT_NAME') === $main->getLastRevision()->getUser()) {
                $id = $main->getLastRevision()->getId();
                $diffStr = sprintf(
                    ' ([https://fr.wikipedia.org/w/index.php?title=%s&diff=%s diff])',
                    str_replace(' ', '_', $mainTitle),
                    $id
                );
            }
        } catch (Throwable $e) {
            unset($e);
        }

        $errorCategoryName = sprintf('Signalement %s', getenv('BOT_NAME'));

        $errorMessage = file_get_contents(self::ERROR_MSG_TEMPLATE);
        $errorMessage = str_replace('##CATEGORY##', $errorCategoryName, $errorMessage);
        $errorMessage = str_replace('##ERROR LIST##', trim($errorList), $errorMessage);
        $errorMessage = str_replace('##ARTICLE##', $mainTitle, $errorMessage);
        $errorMessage = str_replace('##DIFF##', $diffStr, $errorMessage);

        // Edit wiki talk page
        try {
            $talkPage = ServiceFactory::wikiPageAction('Discussion:'.$mainTitle);
            $editInfo = ServiceFactory::editInfo('Signalement erreur {ouvrage}', false, false, 5);

            return $talkPage->addToBottomOrCreatePage($errorMessage, $editInfo);
        } catch (Throwable $e) {
            $log->warning('Exception after addToBottomOrCreatePage() '.$e->getMessage());

            return false;
        }
    }
}
