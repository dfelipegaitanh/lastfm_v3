export interface StatChartSummary {
    id: number;
    user: string;
    from: string;
    to: string;
    synced: boolean;
    preset_type: 'top' | 'viral';
    play_count_limit: number;
    total_charts_synced: number;
    total_playcount: number;
}

export interface StatTrackRow {
    id: number;
    rank: number;
    playcount: number;
    track_name: string;
    track_mbid: string | null;
    last_fm_artist_id: number;
    artist_name: string;
    artist_mbid: string | null;
    artist_image_url: string | null;
    last_fm_album_id: number;
    album_name: string;
    album_mbid: string | null;
    album_image_url: string | null;
    tags: string[];
}

export interface WelcomePageProps {
    chartSummary: StatChartSummary;
    tracks: StatTrackRow[];
}
