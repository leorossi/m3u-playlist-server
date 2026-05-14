<?php

namespace App\Service;

class M3uParser
{
    /**
     * @return array<array{name: string, url: string, tvgId: ?string, tvgName: ?string, tvgLogo: ?string}>
     */
    public function parse(string $content): array
    {
        $channels = [];
        $lines = preg_split('/\r?\n/', trim($content));
        $pending = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line === '#EXTM3U') {
                continue;
            }

            if (str_starts_with($line, '#EXTINF:')) {
                $pending = $this->parseExtinf($line);
                continue;
            }

            if (str_starts_with($line, '#')) {
                continue;
            }

            // Stream URL line
            if ($pending !== null) {
                $pending['url'] = $line;
                $channels[] = $pending;
                $pending = null;
            }
        }

        return $channels;
    }

    private function parseExtinf(string $line): array
    {
        preg_match_all('/(\w[\w-]*)="([^"]*)"/', $line, $matches, PREG_SET_ORDER);
        $attrs = [];
        foreach ($matches as $match) {
            $attrs[strtolower($match[1])] = $match[2];
        }

        // Find first unquoted comma — it separates attributes from display name
        $inQuote = false;
        $commaPos = -1;
        for ($i = 0, $len = strlen($line); $i < $len; $i++) {
            if ($line[$i] === '"') {
                $inQuote = !$inQuote;
            } elseif ($line[$i] === ',' && !$inQuote) {
                $commaPos = $i;
                break;
            }
        }

        $name = $commaPos !== -1 ? trim(substr($line, $commaPos + 1)) : 'Unknown Channel';

        return [
            'name'    => $name !== '' ? $name : 'Unknown Channel',
            'url'     => '',
            'tvgId'   => isset($attrs['tvg-id']) && $attrs['tvg-id'] !== '' ? $attrs['tvg-id'] : null,
            'tvgName' => isset($attrs['tvg-name']) && $attrs['tvg-name'] !== '' ? $attrs['tvg-name'] : null,
            'tvgLogo' => isset($attrs['tvg-logo']) && $attrs['tvg-logo'] !== '' ? $attrs['tvg-logo'] : null,
        ];
    }
}
