<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Infrastructure\Monitor\NullLogger;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * RFC 9309 robots.txt : group selection (bot-specific token, falling back to "*"),
 * longest-match-wins between Allow/Disallow, ties going to Allow (least restrictive).
 * One robots.txt fetch per host per process lifetime (in-memory cache) : this runs
 * ahead of every single URL fetch, so re-fetching per URL would double request volume.
 * Any fetch/parse failure is treated as "no restrictions" — the standard convention,
 * and consistent with not wanting a transient robots.txt hiccup to silently stop the
 * whole crawl.
 */
class RobotsTxtChecker
{
    /** @var array<string, list<array{type: string, pattern: string}>> host => rules for the matched group */
    private array $cache = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string              $botToken,
        private readonly LoggerInterface      $log = new NullLogger()
    )
    {
    }

    public function isAllowed(string $url): bool
    {
        $host = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query !== null && $query !== false) {
            $path .= '?' . $query;
        }

        if (!isset($this->cache[$host])) {
            $this->cache[$host] = $this->fetchRules($host);
        }

        return $this->pathAllowed($path, $this->cache[$host]);
    }

    /**
     * @return list<array{type: string, pattern: string}>
     */
    private function fetchRules(string $host): array
    {
        try {
            $response = $this->httpClient->get($host . '/robots.txt', ['timeout' => 15, 'http_errors' => false]);
            if ($response->getStatusCode() !== 200) {
                return [];
            }
            $body = $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->log->debug('robots.txt fetch failed, treated as no restriction : ' . $host . ' — ' . $e->getMessage());

            return [];
        }

        return $this->selectGroup($this->parseGroups($body));
    }

    /**
     * @return array<string, list<array{type: string, pattern: string}>> lowercased token => rules
     */
    private function parseGroups(string $body): array
    {
        $groups = [];
        $currentTokens = [];
        $groupOpenForNewAgents = true; // consecutive User-agent lines join the same group

        foreach (preg_split('/\R/', $body) as $line) {
            $line = trim((string)preg_replace('/#.*/', '', $line));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if (!$groupOpenForNewAgents) {
                    $currentTokens = [];
                    $groupOpenForNewAgents = true;
                }
                $token = strtolower($value);
                $currentTokens[] = $token;
                $groups[$token] ??= [];
                continue;
            }

            if (($field === 'disallow' || $field === 'allow') && $currentTokens !== []) {
                $groupOpenForNewAgents = false;
                foreach ($currentTokens as $token) {
                    $groups[$token][] = ['type' => $field, 'pattern' => $value];
                }
            }
        }

        return $groups;
    }

    /**
     * @param array<string, list<array{type: string, pattern: string}>> $groups
     * @return list<array{type: string, pattern: string}>
     */
    private function selectGroup(array $groups): array
    {
        return $groups[strtolower($this->botToken)] ?? $groups['*'] ?? [];
    }

    /**
     * @param list<array{type: string, pattern: string}> $rules
     */
    private function pathAllowed(string $path, array $rules): bool
    {
        $bestLength = -1;
        $bestType = 'allow';

        foreach ($rules as $rule) {
            if ($rule['pattern'] === '') {
                continue; // empty Disallow value = no restriction, per spec
            }
            if (!$this->patternMatches($rule['pattern'], $path)) {
                continue;
            }
            $length = strlen($rule['pattern']);
            // tie => least restrictive (Allow) wins, so only overwrite on strictly
            // longer match, or equal length but current best is Disallow
            if ($length > $bestLength || ($length === $bestLength && $rule['type'] === 'allow' && $bestType === 'disallow')) {
                $bestLength = $length;
                $bestType = $rule['type'];
            }
        }

        return $bestLength === -1 || $bestType === 'allow';
    }

    private function patternMatches(string $pattern, string $path): bool
    {
        // "$" is only an end-anchor as the pattern's last character, per spec — anywhere
        // else it's literal, hence stripping/re-adding it explicitly rather than a blind
        // preg_quote(\$) -> "$" replace.
        $endAnchor = str_ends_with($pattern, '$');
        if ($endAnchor) {
            $pattern = substr($pattern, 0, -1);
        }
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . ($endAnchor ? '$' : '') . '#';

        return (bool)preg_match($regex, $path);
    }
}
