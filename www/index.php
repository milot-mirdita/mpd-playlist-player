<?php
require_once __DIR__ . '/../vendor/autoload.php';

const PLAYLIST_PATH = "/var/lib/mpd/playlists";

function getPlaylists($directory) {
    $playlists = [];
    foreach (scandir($directory) as $path) {
        if (in_array($path, [".", ".."])) {
            continue;
        }

        if (!is_file($directory . DIRECTORY_SEPARATOR . $path)) {
            continue;
        }

        $path = str_replace('.m3u', '', $path);
        $playlists[] = $path;
    }

    return $playlists;
}

function playlistsToTree($playlists) {
    $tree = [];
    foreach ($playlists as $path) {
        $pieces = explode('-', $path);
        $category = array_shift($pieces);
        $rest = implode('-', $pieces);
        $rest = str_replace('.m3u', '', $rest);

        if ($rest == '') {
            continue;
        }
        
        if (!isset($tree[$category])) {
            $tree[$category] = [];
        }

        $tree[$category][$rest] = $path;
    }
    return $tree;
}



function play($playlist) {
    exec('mpc clear');
    exec('mpc load ' . escapeshellarg($playlist));
    exec('mpc shuffle');
    exec('mpc play');
    return exec('mpc current');
}

function getCurrent() {
    return exec('mpc current');
}

function playNext() {
    exec('mpc next');
    return exec('mpc current');
}

$klein = new \Klein\Klein();
$klein->respond('POST', '/play', function ($request, $response, $service) {
    $allPlaylists = getPlaylists(PLAYLIST_PATH);
    $playlist = $request->param('playlist', '');
    if ($playlist != '' && in_array($playlist, $allPlaylists)) {
        $response->json([ 'current' => play($playlist) ]);
    }
});


$klein->respond('GET', '/current', function ($request, $response, $service) {
    $response->json([ 'current' => getCurrent() ]);
});

$klein->respond('POST', '/next', function ($request, $response, $service) {
    $response->json([ 'next' => playNext() ]);
});

$klein->respond('GET', '/admin', function ($request, $response, $service) {
    $allPlaylists = getPlaylists(PLAYLIST_PATH);
    $service->admin = true;
    $service->playlists = playlistsToTree($allPlaylists);
    $service->render('../templates/index.phtml');
});

$klein->respond('GET', '/', function ($request, $response, $service) {
    $allPlaylists = getPlaylists(PLAYLIST_PATH);
    $service->admin = false;
    $service->playlists = playlistsToTree($allPlaylists);
    $service->render('../templates/index.phtml');
});

$klein->dispatch();
