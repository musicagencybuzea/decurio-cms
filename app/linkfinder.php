<?php
declare(strict_types=1);

/**
 * Automatic link finder - free public APIs only, results must be
 * reviewed by a human before anything is published.
 *
 * Two modes:
 *  A) With Spotify credentials in config.php (client id + secret):
 *     Spotify URL -> title/artists/UPC -> Apple Music, Deezer, YouTube.
 *  B) Keyless fallback (no credentials - Spotify now requires a Premium account
 *     on the dashboard owner): public Spotify oEmbed gives title + cover,
 *     then Deezer + iTunes searches find the other platform links.
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

/** Apple Music (+ iTunes Store) link: UPC lookup first, then a title search. */
function find_apple(array &$results, string $upc, string $artists, string $title): void
{
    $apple = null;
    if ($upc !== '') {
        $r = http_json('GET', 'https://itunes.apple.com/lookup?upc=' . rawurlencode($upc) . '&entity=album&limit=1');
        if (isset($r['results'][0]['collectionViewUrl'])) $apple = $r['results'][0];
    }
    if (!$apple) {
        $q = rawurlencode(trim($artists . ' ' . $title));
        $r = http_json('GET', "https://itunes.apple.com/search?term={$q}&entity=album&limit=1");
        if (isset($r['results'][0]['collectionViewUrl'])) $apple = $r['results'][0];
    }
    if ($apple) {
        $results[] = ['platform' => 'Apple Music', 'url' => $apple['collectionViewUrl'], 'source' => 'itunes', 'found' => true];
        if (!empty($apple['trackViewUrl'])) {
            $results[] = ['platform' => 'iTunes Store', 'url' => $apple['trackViewUrl'], 'source' => 'itunes', 'found' => true];
        }
    }
}

/** YouTube Music link (needs a free YouTube Data API key, optional). */
function find_youtube(array &$results, string $configKey, string $artists, string $title): void
{
    if ($configKey === '') return;
    $q = rawurlencode(trim($artists . ' ' . $title) . ' topic');
    $r = http_json('GET', 'https://www.googleapis.com/youtube/v3/search?part=snippet&type=video&maxResults=1&q=' . $q . '&key=' . rawurlencode($configKey));
    if (!empty($r['items'][0]['id']['videoId'])) {
        $vid = $r['items'][0]['id']['videoId'];
        $results[] = ['platform' => 'YouTube Music', 'url' => 'https://music.youtube.com/watch?v=' . $vid, 'source' => 'youtube', 'found' => true];
    }
}

/**
 * Keyless mode: public Spotify oEmbed (title + cover), then Deezer search
 * (link + artist + UPC) and iTunes. No keys, no accounts, no Premium.
 */
function find_links_keyless(array $ids, string $youtubeKey): array
{
    $type = $ids['type'] === 'track' ? 'track' : 'album';
    $spotifyUrl = 'https://open.spotify.com/' . $type . '/' . $ids['id'];

    $o = http_json('GET', 'https://open.spotify.com/oembed?url=' . rawurlencode($spotifyUrl));
    $title = trim((string)($o['title'] ?? ''));
    $cover = (string)($o['thumbnail_url'] ?? '');
    if ($title === '') return ['error' => 'not_found'];

    $artists = '';
    $upc = '';

    if ($type === 'album') {
        // Deezer album search -> top result: link, artist, then full album (for the UPC)
        $q = rawurlencode($title);
        $r = http_json('GET', "https://api.deezer.com/search/album?q={$q}&limit=1");
        $dzId = (int)($r['data'][0]['id'] ?? 0);
        if ($dzId > 0) {
            $artists = (string)($r['data'][0]['artist']['name'] ?? '');
            $full = http_json('GET', 'https://api.deezer.com/album/' . $dzId);
            if (!empty($full['link'])) {
                $upc = (string)($full['upc'] ?? '');
                if ($artists === '') $artists = (string)($full['artist']['name'] ?? '');
                if ($cover === '' && !empty($full['cover_xl'])) $cover = (string)$full['cover_xl'];
            }
        }
    } else {
        // track link: Deezer track search -> its album
        $q = rawurlencode($title);
        $r = http_json('GET', "https://api.deezer.com/search/track?q={$q}&limit=1");
        $t = $r['data'][0] ?? [];
        if ($t) {
            $artists = (string)($t['artist']['name'] ?? '');
            $dzId = (int)($t['album']['id'] ?? 0);
            if ($dzId > 0) {
                $full = http_json('GET', 'https://api.deezer.com/album/' . $dzId);
                if (!empty($full['link'])) {
                    $upc = (string)($full['upc'] ?? '');
                    if ($cover === '' && !empty($t['album']['cover_xl'])) $cover = (string)$t['album']['cover_xl'];
                }
            }
        }
    }

    $results = [['platform' => 'Spotify', 'url' => $spotifyUrl, 'source' => 'spotify', 'found' => true]];

    if ($upc !== '') {
        $r = http_json('GET', 'https://api.deezer.com/album/upc/' . rawurlencode($upc));
        if (!empty($r['link'])) {
            $results[] = ['platform' => 'Deezer', 'url' => $r['link'], 'source' => 'deezer', 'found' => true];
        } else {
            $r = http_json('GET', 'https://api.deezer.com/album/' . (int)($dzId ?? 0));
            if (!empty($r['link'])) {
                $results[] = ['platform' => 'Deezer', 'url' => $r['link'], 'source' => 'deezer', 'found' => true];
            }
        }
    }

    find_apple($results, $upc, $artists, $title);
    find_youtube($results, $youtubeKey, $artists, $title);

    return [
        'album' => ['title' => $title, 'artists' => $artists, 'upc' => $upc, 'cover' => $cover],
        'results' => $results,
    ];
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
    if (!$ids) return ['error' => 'invalid_url'];

    $youtubeKey = (string)($config['youtube_api_key'] ?? '');

    // mode A: with Spotify credentials (works even for track -> album resolution)
    $token = spotify_token($config);
    if ($token !== null) {
        if ($ids['type'] === 'track') {
            $tr = http_json('GET', 'https://api.spotify.com/v1/tracks/' . rawurlencode($ids['id']), ['Authorization: Bearer ' . $token]);
            if (!isset($tr['album']['id'])) return ['error' => 'not_found'];
            $ids = ['type' => 'album', 'id' => $tr['album']['id']];
        }

        $album = http_json('GET', 'https://api.spotify.com/v1/albums/' . rawurlencode($ids['id']), ['Authorization: Bearer ' . $token]);
        if (!isset($album['name'])) return ['error' => 'not_found'];

        $title = (string)($album['name'] ?? '');
        $artists = implode(', ', array_map(fn($a) => $a['name'] ?? '', $album['artists'] ?? []));
        $upc = $album['external_ids']['upc'] ?? '';

        $results = [];
        $results[] = ['platform' => 'Spotify', 'url' => 'https://open.spotify.com/album/' . $ids['id'], 'source' => 'spotify', 'found' => true];

        find_apple($results, (string)$upc, $artists, $title);

        // Deezer (no key). UPC lookup first, then a search fallback if it failed.
        $deezerDone = false;
        $q = rawurlencode($artists . ' ' . $title);
        if ((string)$upc !== '') {
            $r = http_json('GET', 'https://api.deezer.com/album/upc/' . rawurlencode((string)$upc));
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

        find_youtube($results, $youtubeKey, $artists, $title);

        return [
            'album' => ['title' => $title, 'artists' => $artists, 'upc' => (string)$upc, 'cover' => $album['images'][0]['url'] ?? ''],
            'results' => $results,
        ];
    }

    // mode B: keyless (no Spotify credentials - Premium no longer required of YOU)
    return find_links_keyless($ids, $youtubeKey);
}
