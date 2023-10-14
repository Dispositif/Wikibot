<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Application\Utils\HttpUtil;
use App\Domain\InfrastructurePorts\InternetDomainParserInterface;
use App\Infrastructure\Monitor\NullLogger;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Todo move infra ?
 */
class CheckURL
{
    /**
     * @var LoggerInterface
     */
    protected $log;

    /**
     * @var string
     */
    protected $registrableDomain;
    /**
     * @var string
     */
    protected $url;
    /**
     * @var InternetDomainParserInterface
     */
    protected $internetDomainParser;

    public function __construct(InternetDomainParserInterface $domainParser, ?LoggerInterface $logger = null)
    {
        $this->log = $logger ?? new NullLogger();
        $this->internetDomainParser = $domainParser;
    }

    public function isURLAuthorized(string $url): bool
    {
        $this->url = $url;
        $this->registrableDomain = null;
        if (!HttpUtil::isHttpURL($url)) {
            $this->log->debug('Skip : not Http URL : ' . $url, ['stats' => 'externref.skip.notRawURL']);
            return false;
        }

        if ($this->hasForbiddenFilenameExtension()) {
            $this->log->debug('Skip : ForbiddenFilenameExtension : ' . $url, ['stats' => 'externref.skip.forbiddenFilenameExtension']);
            return false;
        }

        $this->findRegistrableDomain();

        return true;
    }

    /**
     * todo move URL parsing
     * Skip PDF GIF etc
     * https://fr.wikipedia.org/wiki/Liste_d%27extensions_de_fichiers
     */
    protected function hasForbiddenFilenameExtension(): bool
    {
        return (bool)preg_match(
            '#\.(pdf|jpg|jpeg|gif|png|webp|xls|xlsx|xlr|xml|xlt|txt|csv|js|docx|exe|gz|zip|ini|movie|mp3|mp4|ogg|raw|rss|tar|tgz|wma)$#i',
            $this->url
        );
    }

    protected function findRegistrableDomain(): ?string
    {
        try {
            $this->registrableDomain = $this->internetDomainParser->getRegistrableDomainFromURL($this->url);
        } catch (Exception) {
            $this->log->warning('Skip : not a valid URL : ' . $this->url,
                ['stats' => 'externref.skip.URLAuthorized.exception2']
            );
            return null;
        }
        return $this->registrableDomain;
    }

    public function getRegistrableDomain($url): ?string
    {
        if ($url === $this->url && $this->registrableDomain) {
            return $this->registrableDomain;
        }
        $this->url = $url;

        return $this->findRegistrableDomain();
    }
}