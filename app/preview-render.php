<?php
declare(strict_types=1);

/**
 * Shared live-preview renderer. Included by index.php (?preview) and by the
 * dedicated root entry point preview-page.php (which host caches cannot confuse
 * with the homepage, whatever their caching strategy is).
 */

    if (logged_in() && !empty($_SESSION['preview_snapshot'])) {
        $snap = $_SESSION['preview_snapshot'];
        $previewAlbum = null;
        if (!empty($snap['id'])) {
            $q = db()->prepare('SELECT * FROM albums WHERE id=?');
            $q->execute([(int)$snap['id']]);
            $previewAlbum = $q->fetch();
        }
        // Live preview: the form state as it is on screen right now, saved or not
        $fields = ['title', 'subtitle', 'artists', 'release_number', 'links_json', 'socials_json',
                   'extra_socials_json', 'sticker_slots_json', 'bg_preset', 'video_id', 'box_color', 'box_transparent',
                   'link_text', 'release_date', 'status', 'publish_at'];
        if (!$previewAlbum && !empty($snap['title'])) {
            $previewAlbum = array_fill_keys([
                'id', 'title', 'subtitle', 'is_single', 'release_number', 'artists', 'cover_path', 'links_json',
                'socials_json', 'extra_socials_json', 'sticker_slots_json', 'bg_preset', 'video_id', 'box_color',
                'box_transparent', 'link_text', 'music_id', 'publish_at', 'status', 'sort_order', 'created_at', 'updated_at', 'release_date'], '');
            $previewAlbum['id'] = 0;
            $previewAlbum['is_single'] = 0;
            $previewAlbum['box_transparent'] = 0;
            $previewAlbum['music_id'] = null;
        }
        if ($previewAlbum) {
            foreach ($fields as $f) {
                if (array_key_exists($f, $snap)) $previewAlbum[$f] = $snap[$f];
            }
            if (!empty($snap['cover_path'])) $previewAlbum['cover_path'] = $snap['cover_path']; // freshly chosen cover
            $previewAlbum['status'] = 'published'; // preview always renders like a live page
            require __DIR__ . '/release-page.php';
            render_release_page($previewAlbum, true);
        }
    }
    fail('Preview not found.');
