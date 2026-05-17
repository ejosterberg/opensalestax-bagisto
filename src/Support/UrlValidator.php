<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Support;

use InvalidArgumentException;

/**
 * SSRF-defense URL validator for the OST engine base URL.
 *
 * Rules (in order):
 *  1. Empty input â€” reject.
 *  2. Parse failure or missing scheme/host â€” reject.
 *  3. Scheme must be `http` or `https` â€” reject otherwise.
 *  4. When `allowPrivateNets` is FALSE (default), every resolved IP must be
 *     public â€” reject if any resolved address falls in:
 *       - RFC1918    (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
 *       - loopback   (127.0.0.0/8, ::1)
 *       - link-local (169.254.0.0/16, fe80::/10) â€” including the AWS metadata
 *                    endpoint at 169.254.169.254
 *       - CGNAT      (100.64.0.0/10, RFC 6598)
 *       - multicast  (224.0.0.0/4, ff00::/8)
 *
 * Default-strict because the dominant attack class against admin-controlled
 * URLs is SSRF: an attacker who can edit the env or config file can otherwise
 * direct the package at internal services (Redis, intranet apps, cloud
 * metadata).
 *
 * Merchants who legitimately self-host OST on the same LAN as Bagisto opt in
 * by setting `OPENSALESTAX_ALLOW_PRIVATE_NETS=true`.
 */
final class UrlValidator
{
    /**
     * @param callable(string):string[] $hostResolver Function returning the
     *     list of resolved IPv4/IPv6 addresses for a hostname, or [] on
     *     failure. The default uses gethostbynamel; tests inject a mock.
     */
    public function __construct(
        private readonly bool $allowPrivateNets,
        private $hostResolver = null,
    ) {
        if ($this->hostResolver === null) {
            $this->hostResolver = static function (string $host): array {
                // Literal IP: skip DNS.
                if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                    return [$host];
                }
                $list = gethostbynamel($host);
                return $list === false ? [] : $list;
            };
        }
    }

    /**
     * @throws InvalidArgumentException With a human-readable rejection reason.
     */
    public function validate(string $url): void
    {
        if ($url === '') {
            throw new InvalidArgumentException('The OpenSalesTax engine base URL is empty.');
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme']) || !isset($parts['host'])) {
            throw new InvalidArgumentException(
                'The OpenSalesTax engine base URL must be fully-qualified (e.g. https://ost.example.com).',
            );
        }

        if (!in_array($parts['scheme'], ['http', 'https'], true)) {
            throw new InvalidArgumentException(
                'The OpenSalesTax engine base URL must use http or https.',
            );
        }

        if ($this->allowPrivateNets) {
            return;
        }

        $this->rejectPrivateHost((string) $parts['host']);
    }

    /**
     * @throws InvalidArgumentException When the host resolves to any
     *     private / reserved IP range, or fails to resolve at all.
     */
    private function rejectPrivateHost(string $host): void
    {
        /** @var callable(string):string[] $resolver */
        $resolver = $this->hostResolver;
        $ips = $resolver($host);

        if ($ips === []) {
            throw new InvalidArgumentException(
                'The OpenSalesTax engine base URL host could not be resolved.',
            );
        }

        foreach ($ips as $ip) {
            if (!self::isPublic($ip)) {
                throw new InvalidArgumentException(
                    'The OpenSalesTax engine base URL resolves to a private / reserved IP. ' .
                    'Set OPENSALESTAX_ALLOW_PRIVATE_NETS=true to permit private-LAN engines.',
                );
            }
        }
    }

    private static function isPublic(string $ip): bool
    {
        $valid = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
        // CGNAT (100.64.0.0/10, RFC 6598) is not flagged by NO_RES_RANGE; PHP also
        // doesn't consistently reject IPv4 multicast (224.0.0.0/4) under NO_RES_RANGE.
        // Both are checked explicitly.
        return $valid !== false && !self::isCgnat($ip) && !self::isIpv4Multicast($ip);
    }

    private static function isCgnat(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }
        // 100.64.0.0/10 â†’ 100.64.0.0 (0x64400000) .. 100.127.255.255 (0x647FFFFF)
        return $long >= 0x64400000 && $long <= 0x647FFFFF;
    }

    private static function isIpv4Multicast(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }
        // 224.0.0.0/4 â†’ 224.0.0.0 (0xE0000000) .. 239.255.255.255 (0xEFFFFFFF)
        return $long >= 0xE0000000 && $long <= 0xEFFFFFFF;
    }
}
