<?php

declare(strict_types=1);

namespace App\Data\LastFm;

use Illuminate\Support\Collection;

final readonly class WeeklyTrackData
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $artist,
        public ?string $artistMbid,
        public string $track,
        public ?string $trackMbid,
        public string $album,
        public ?string $albumMbid,
        public int $playcount,
        public int $rank,
        public array $tags = [],
    ) {}

    public static function fromApi(array $track, array|Collection|null $trackInfo = null): self
    {
        $artist = (string) (data_get($track, 'artist.#text') ?? data_get($track, 'artist.name') ?? data_get($track, 'artist') ?? '');
        $artistMbid = data_get($track, 'artist.mbid');
        $artistMbid = is_string($artistMbid) && mb_trim($artistMbid) !== '' ? mb_trim($artistMbid) : null;

        $trackName = (string) (data_get($track, 'name') ?? data_get($track, 'track') ?? '');
        $trackMbid = data_get($track, 'mbid');
        $trackMbid = is_string($trackMbid) && mb_trim($trackMbid) !== '' ? mb_trim($trackMbid) : null;

        $playcount = (int) (data_get($track, 'playcount') ?? 0);
        $rank = (int) (data_get($track, '@attr.rank') ?? data_get($track, 'rank') ?? 0);

        $albumTitle = 'Unknown Album';
        $albumMbid = null;
        $tags = [];

        if ($trackInfo !== null) {
            $rawAlbum = data_get($trackInfo, 'album');
            $extractedTitle = data_get($rawAlbum, 'title');
            if (is_string($extractedTitle) && mb_trim($extractedTitle) !== '') {
                $albumTitle = mb_trim($extractedTitle);
            }

            $extractedAlbumMbid = data_get($rawAlbum, 'mbid');
            if (is_string($extractedAlbumMbid) && mb_trim($extractedAlbumMbid) !== '') {
                $albumMbid = mb_trim($extractedAlbumMbid);
            }

            $rawTags = data_get($trackInfo, 'toptags.tag', []);
            $tags = self::normalizeTags($rawTags);
        } else {
            $rawAlbum = data_get($track, 'album');
            if (is_string($rawAlbum) && mb_trim($rawAlbum) !== '') {
                $albumTitle = mb_trim($rawAlbum);
            } elseif (is_array($rawAlbum)) {
                $extractedTitle = data_get($rawAlbum, '#text') ?? data_get($rawAlbum, 'title');
                if (is_string($extractedTitle) && mb_trim($extractedTitle) !== '') {
                    $albumTitle = mb_trim($extractedTitle);
                }

                $extractedAlbumMbid = data_get($rawAlbum, 'mbid');
                if (is_string($extractedAlbumMbid) && mb_trim($extractedAlbumMbid) !== '') {
                    $albumMbid = mb_trim($extractedAlbumMbid);
                }
            }

            $rawAlbumMbid = data_get($track, 'album_mbid');
            if ($albumMbid === null && is_string($rawAlbumMbid) && mb_trim($rawAlbumMbid) !== '') {
                $albumMbid = mb_trim($rawAlbumMbid);
            }

            $rawTags = data_get($track, 'tags', []);
            $tags = self::normalizeTags($rawTags);
        }

        return new self(
            artist: $artist,
            artistMbid: $artistMbid,
            track: $trackName,
            trackMbid: $trackMbid,
            album: $albumTitle,
            albumMbid: $albumMbid,
            playcount: $playcount,
            rank: $rank,
            tags: $tags,
        );
    }

    /**
     * @return list<string>
     */
    private static function normalizeTags(mixed $rawTags): array
    {
        if ($rawTags instanceof Collection) {
            $rawTags = $rawTags->all();
        }

        if (! is_array($rawTags)) {
            return [];
        }

        if (isset($rawTags['name']) && is_string($rawTags['name'])) {
            $name = mb_trim($rawTags['name']);

            return $name !== '' ? [$name] : [];
        }

        $tags = [];
        foreach ($rawTags as $tag) {
            if (is_array($tag)) {
                $name = data_get($tag, 'name');
                if (is_string($name) && mb_trim($name) !== '') {
                    $tags[] = mb_trim($name);
                }
            } elseif (is_string($tag) && mb_trim($tag) !== '') {
                $tags[] = mb_trim($tag);
            }
        }

        return array_values(array_unique($tags));
    }
}
