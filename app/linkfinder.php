<?php
declare(strict_types=1);

/**
 * Automatic link finder – free public APIs only, results must be
 * reviewed by a human before anything is published.
 *
 * Pipeline: Spotify URL -> title/artists/UPC -> Apple Music (iTunes Search API),
 * Deezer API, YouTube (search link built via YouTube Data API if a key is set).
 */

function http_json(string $method, string $url, array $headers = [], ?string $body = null): ?array
{
    $data = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false || $code >= 400) return null;
        $data = $raw;
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'timeout' => 15,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return null;
        $data = $raw;
    }
    $json = json_decode($data, true);
    return is_array($json) ? $json : null;
}

function find_spotify_ids(string $url): ?array
{
    if (!preg_match('#open\.spotify\.com/(?:intl-[a-z]{2,5}/)?(album|track)/([A-Za-z0-9]+)#', $url, $m)) {
        return null;
    }
    return ['type' => $m[1], 'id' => $m[2]];
}

function spotify_token(array $config): ?string
{
    $id = $config['spotify_client_id'] ?? '';
    $secret = $config['spotify_client_secret'] ?? '';
    if ($id === '' || $secret === '') return null;
    $res = http_json('POST', 'https://accounts.spotify.com/api/token', [
        'Authorization: Basic ' . base64_encode($id . ':' . $secret),
        'Content-Type: application/x-www-form-urlencoded',
    ], http_build_query(['grant_type' => 'client_credentials']));
    return $res['access_token'] ?? null;
}

/**
 * @return array{album: array, results: list<array{platform:string,url:string,source:string}>}|array{error:string}
 */
function find_links(string $url, array $config): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['error' => 'invalid_url'];
    }
    $ids = find_spotify_ids($url);
    if (!$ids || $ids['type'] !== 'album') {
        // allow a track link: resolve to its album
        $token = spotify_token($config);
        if (!$token) return ['error' => 'api_missing'];
        if ($ids && $ids['type'] === 'track') {
            $tr = http_json('GET', 'https://api.spotify.com/v1/tracks/' . rawurlencode($ids['id']), ['Authorization: Bearer ' . $token]);
            if (!isset($tr['album']['id'])) return ['error' => 'not_found'];
            $ids = ['type' => 'album', 'id' => $tr['album']['id']];
        } else {
            return ['error' => 'invalid_url'];
        }
    }

    $token = spotify_token($config);
    if (!$token) return ['error' => 'api_missing'];
    $album = http_json('GET', 'https://api.spotify.com/v1/albums/' . rawurlencode($ids['id']), ['Authorization: Bearer ' . $token]);
    if (!isset($album['name'])) return ['error' => 'not_found'];

    $title = (string)($album['name'] ?? '');
    $artists = implode(', ', array_map(fn($a) => $a['name'] ?? '', $album['artists'] ?? []));
    $upc = $album['external_ids']['upc'] ?? '';

    $results = [];
    $results[] = ['platform' => 'Spotify', 'url' => 'https://open.spotify.com/album/' . $ids['id'], 'source' => 'spotify', 'found' => true];

    // Apple Music + iTunes Store (iTunes Search API, no key)
    $apple = null;
    if ($upc !== '') {
        $r = http_json('GET', 'https://itunes.apple.com/lookup?upc=' . rawurlencode($upc) . '&entity=album&limit=1');
        if (isset($r['results'][0]['collectionViewUrl'])) $apple = $r['results'][0];
    }
    if (!$apple) {
        $q = rawurlencode($artists . ' ' . $title);
        $r = http_json('GET', "https://itunes.apple.com/search?term={$q}&entity=album&limit=1");
        if (isset($r['results'][0]['collectionViewUrl'])) $apple = $r['results'][0];
    }
    if ($apple) {
        $results[] = ['platform' => 'Apple Music', 'url' => $apple['collectionViewUrl'], 'source' => 'itunes', 'found' => true];
        if (!empty($apple['trackViewUrl'])) {
            $results[] = ['platform' => 'iTunes Store', 'url' => $apple['trackViewUrl'], 'source' => 'itunes', 'found' => true];
        }
    }

    // Deezer (no key). UPC lookup first, then a search fallback if it failed.
    $deezerDone = false;
    $q = rawurlencode($artists . ' ' . $title);
    if ($upc !== '') {
        $r = http_json('GET', 'https://api.deezer.com/album/upc/' . rawurlencode($upc));
        if (!empty($r['link'])) {
            $results[] = ['platform' => 'Deezer', 'url' => $r['link'], 'source' => 'deezer', 'found' => true];
            $deezerDone = true;
        }
    }
    if (!$deezerDone) {
        $r = http_json('GET', "https://api.deezer.com/search/album?q={$q}&limit=1");
        if (!empty($r['data'][0]['link'])) {
            $results[] = ['platform' => 'Deezer', 'url' => $r['data'][0]['link'], 'source' => 'deezer', 'found' => true];
        }
    }

    // YouTube Music (needs a free YouTube Data API key)
    $ytKey = $config['youtube_api_key'] ?? '';
    if ($ytKey !== '') {
        $q = rawurlencode($artists . ' ' . $title . ' topic');
        $r = http_json('GET', "https://www.googleapis.com/youtube/v3/search?part=snippet&type=video&maxResults=1&q={$q}&key=" . rawurlencode($ytKey));
        if (!empty($r['items'][0]['id']['videoId'])) {
            $vid = $r['items'][0]['id']['videoId'];
            $results[] = ['platform' => 'YouTube Music', 'url' => 'https://music.youtube.com/watch?v=' . $vid, 'source' => 'youtube', 'found' => true];
        }
    }

    return [
        'album' => ['title' => $title, 'artists' => $artists, 'upc' => $upc],
        'results' => $results,
    ];
}
