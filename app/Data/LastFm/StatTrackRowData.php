<?php

declare(strict_types=1);

namespace App\Data\LastFm;

use App\Data\Data;

final readonly class StatTrackRowData extends Data
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public int $id,
        public int $rank,
        public int $playcount,
        public string $track_name,
        public ?string $track_mbid,
        public int $last_fm_artist_id,
        public string $artist_name,
        public ?string $artist_mbid,
        public ?string $artist_image_url,
        public int $last_fm_album_id,
        public string $album_name,
        public ?string $album_mbid,
        public ?string $album_image_url,
        public array $tags,
    ) {}
}
