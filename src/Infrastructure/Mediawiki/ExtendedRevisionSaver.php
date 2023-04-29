<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Mediawiki;

use Mediawiki\Api\Service\RevisionSaver;
use Mediawiki\Api\SimpleRequest;
use Mediawiki\DataModel\EditInfo;
use Mediawiki\DataModel\Revision;
use RuntimeException;

/**
 * @access private
 * @author Addshore
 * @author DFelten (EditInfo fix)
 */
class ExtendedRevisionSaver extends RevisionSaver
{
    /**
     * @var mixed
     */
    protected $errors;

    /**
     * @since 0.2
     */
    public function save(Revision $revision, EditInfo $editInfo = null): bool
    {
        $editInfo = $editInfo ?: $revision->getEditInfo();

        $result = $this->api->postRequest(
            new SimpleRequest('edit', $this->getEditParams($revision, $editInfo))
        );
        $success = ($result['edit']['result'] == 'Success');
        if (!$success) {
            $this->errors = $result;
        }

        return $success;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }


    /**
     * @throws RuntimeException
     */
    private function getEditParams(Revision $revision, EditInfo $editInfo = null): array
    {
        if (!$revision->getPageIdentifier()->identifiesPage()) {
            throw new RuntimeException('$revision PageIdentifier does not identify a page');
        }

        $params = [];

        $content = $revision->getContent();
        $data = $content->getData();
        if (!is_string($data)) {
            throw new RuntimeException('Dont know how to save content of this model.');
        }
        $params['text'] = $content->getData();
        $params['md5'] = md5((string) $content->getData());

        $timestamp = $revision->getTimestamp();
        if (!is_null($timestamp)) {
            $params['basetimestamp'] = $timestamp;
        }

        if (!is_null($revision->getPageIdentifier()->getId())) {
            $params['pageid'] = $revision->getPageIdentifier()->getId();
        } else {
            $params['title'] = $revision->getPageIdentifier()->getTitle()->getTitle();
        }

        $params['token'] = $this->api->getToken();

        if ($this->api->isLoggedin()) {
            $params['assert'] = 'user';
        }

        $this->addEditInfoParams($editInfo, $params);

        return $params;
    }

    private function addEditInfoParams(?EditInfo $editInfo, array &$params): void
    {
        if (!is_null($editInfo)) {
            $params['summary'] = $editInfo->getSummary();
            if ($editInfo->getMinor()) {
                $params['minor'] = true;
            }
            if ($editInfo->getBot()) {
                $params['bot'] = true;
                $params['assert'] = 'bot';
            }
        }
    }
}
