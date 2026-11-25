<?php
// Copy this file to config.php and fill in the credentials from your hosting control panel.
//
// Optional – automatic link finder (all free):
// 1. Create a (free) app at https://developer.spotify.com/dashboard and copy its
//    Client ID + Client Secret below. Needed to read title/artist/UPC from a Spotify link.
// 2. Optional: create a (free) YouTube Data API key at https://console.cloud.google.com
//    to search YouTube Music. Without it, YouTube links are simply skipped.
return [
    'db_host' => 'localhost',
    'db_name' => 'YOUR_DATABASE_NAME',
    'db_user' => 'YOUR_DATABASE_USER',
    'db_pass' => 'YOUR_DATABASE_PASSWORD',
    'spotify_client_id' => '',
    'spotify_client_secret' => '',
    'youtube_api_key' => '',
];
