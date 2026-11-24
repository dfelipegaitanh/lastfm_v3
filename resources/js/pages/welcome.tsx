import { Head } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle2,
    Clock,
    Disc3,
    Fingerprint,
    Headphones,
    Layers,
    Moon,
    Music2,
    Search,
    Sparkles,
    Sun,
    Trophy,
    User,
    X,
} from 'lucide-react';
import * as React from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { WelcomePageProps } from '@/types';

/**
 * Implementación del Two-State Toggle según `dark-mode-toggle` y `dark-mode`:
 * - Estado 1: System default (sin valor en localStorage, <meta name="color-scheme" content="light dark">)
 * - Estado 2: Override explícito ('light' o 'dark' en localStorage y en <meta name="color-scheme">)
 * - Si al alternar el esquema objetivo coincide con el del sistema operativo, borra localStorage para volver a System.
 */
function ColorSchemeToggle() {
    const [storedOverride, setStoredOverride] = React.useState<
        'light' | 'dark' | null
    >(() => {
        if (typeof window === 'undefined') {
            return null;
        }

        try {
            const val = localStorage.getItem('color-scheme');

            return val === 'light' || val === 'dark' ? val : null;
        } catch {
            return null;
        }
    });

    const [systemScheme, setSystemScheme] = React.useState<'light' | 'dark'>(
        () => {
            if (typeof window === 'undefined') {
                return 'dark';
            }

            return window.matchMedia('(prefers-color-scheme: dark)').matches
                ? 'dark'
                : 'light';
        },
    );

    React.useEffect(() => {
        const mql = window.matchMedia('(prefers-color-scheme: dark)');
        const onSystemChange = (e: MediaQueryListEvent) => {
            setSystemScheme(e.matches ? 'dark' : 'light');
        };
        mql.addEventListener('change', onSystemChange);

        const onStorage = (e: StorageEvent) => {
            if (e.key === 'color-scheme') {
                const next =
                    e.newValue === 'light' || e.newValue === 'dark'
                        ? e.newValue
                        : null;
                setStoredOverride(next);
                const meta = document.querySelector(
                    'meta[name="color-scheme"]',
                );
                if (meta) {
                    meta.setAttribute('content', next ?? 'light dark');
                }
            }
        };
        window.addEventListener('storage', onStorage);

        return () => {
            mql.removeEventListener('change', onSystemChange);
            window.removeEventListener('storage', onStorage);
        };
    }, []);

    const renderedScheme = storedOverride ?? systemScheme;

    const handleToggle = () => {
        const targetScheme: 'light' | 'dark' =
            renderedScheme === 'dark' ? 'light' : 'dark';
        const currentSys: 'light' | 'dark' = window.matchMedia(
            '(prefers-color-scheme: dark)',
        ).matches
            ? 'dark'
            : 'light';
        const meta = document.querySelector('meta[name="color-scheme"]');

        if (targetScheme === currentSys) {
            try {
                localStorage.removeItem('color-scheme');
            } catch {
                // Ignore storage errors
            }

            setStoredOverride(null);

            if (meta) {
                meta.setAttribute('content', 'light dark');
            }
        } else {
            try {
                localStorage.setItem('color-scheme', targetScheme);
            } catch {
                // Ignore storage errors
            }

            setStoredOverride(targetScheme);

            if (meta) {
                meta.setAttribute('content', targetScheme);
            }
        }
    };

    return (
        <button
            type="button"
            onClick={handleToggle}
            className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            title={
                storedOverride
                    ? `Tema fijado en ${renderedScheme === 'dark' ? 'Oscuro' : 'Claro'} (clic para volver a Sistema)`
                    : `Siguiendo al Sistema (${renderedScheme === 'dark' ? 'Oscuro' : 'Claro'})`
            }
        >
            {renderedScheme === 'dark' ? (
                <>
                    <Sun className="size-3.5 text-amber-400" />
                    <span>Modo Claro</span>
                </>
            ) : (
                <>
                    <Moon className="size-3.5 text-slate-700" />
                    <span>Modo Oscuro</span>
                </>
            )}
            <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground">
                {storedOverride ? 'Fijo' : 'Auto'}
            </span>
        </button>
    );
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function formatShortDate(dateString: string): string {
    const parsed = new Date(`${dateString}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return dateString;
    }

    return new Intl.DateTimeFormat('es-CO', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsed);
}

export default function Welcome({ chartSummary, tracks }: WelcomePageProps) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [selectedTag, setSelectedTag] = React.useState<string | null>(null);

    const allTags = Array.from(
        new Set(tracks.flatMap((track) => track.tags)),
    ).sort();

    const maxPlaycount = Math.max(
        1,
        ...tracks.map((track) => track.playcount),
    );

    const normalizedQuery = searchQuery.trim().toLowerCase();

    const filteredTracks = tracks.filter((track) => {
        const matchesTag = selectedTag
            ? track.tags.includes(selectedTag)
            : true;

        if (!matchesTag) {
            return false;
        }

        if (!normalizedQuery) {
            return true;
        }

        return (
            track.track_name.toLowerCase().includes(normalizedQuery) ||
            track.artist_name.toLowerCase().includes(normalizedQuery) ||
            track.album_name.toLowerCase().includes(normalizedQuery) ||
            track.tags.some((tag) =>
                tag.toLowerCase().includes(normalizedQuery),
            )
        );
    });

    return (
        <>
            <Head
                title={`Estadísticas Semanales (@${chartSummary.user}) - LastFM v3`}
            />

            <div className="flex min-h-dvh flex-col justify-between bg-background p-4 font-sans text-foreground antialiased transition-colors duration-200 selection:bg-red-500/30 sm:p-6 lg:p-10">
                {/* Header Superior Limpio (Cero Auth / Cero Login) */}
                <header className="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 border-b border-border pb-6">
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-xl border border-red-500/30 bg-linear-to-br from-red-600 to-red-900 text-white shadow-md">
                            <Disc3 className="size-5 animate-[spin_6s_linear_infinite]" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-base font-bold tracking-tight text-foreground">
                                    LastFM v3
                                </h1>
                                <span className="inline-flex items-center gap-1 rounded-full border border-red-500/25 bg-red-500/10 px-2 py-0.5 text-[10px] font-semibold text-red-600">
                                    <Sparkles className="size-2.5" />
                                    Zero-Login
                                </span>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Estadísticas Semanales & Dominio Last.fm
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <span className="hidden items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium text-muted-foreground sm:inline-flex">
                            <span
                                className={cn(
                                    'size-2 rounded-full',
                                    chartSummary.synced
                                        ? 'animate-pulse bg-emerald-500'
                                        : 'bg-amber-500',
                                )}
                            />
                            Chart #{chartSummary.id} • {tracks.length} Tracks
                        </span>
                        <ColorSchemeToggle />
                    </div>
                </header>

                {/* Contenido Principal */}
                <main className="mx-auto my-8 flex w-full max-w-6xl flex-1 flex-col gap-6">
                    {/* 4-Card Summary Strip */}
                    <section
                        aria-label="Resumen del chart semanal"
                        className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        {/* Card 1: User & Preset */}
                        <Card className="gap-3 py-4">
                            <CardHeader className="px-5 pb-0">
                                <CardDescription className="flex items-center justify-between text-xs">
                                    <span>Usuario & Preset</span>
                                    <User className="size-3.5 text-muted-foreground" />
                                </CardDescription>
                                <CardTitle className="truncate text-lg font-bold">
                                    @{chartSummary.user}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-wrap items-center gap-1.5 px-5">
                                <Badge
                                    variant={
                                        chartSummary.preset_type === 'top'
                                            ? 'default'
                                            : 'secondary'
                                    }
                                    className="uppercase"
                                >
                                    {chartSummary.preset_type}
                                </Badge>
                                <Badge variant="outline" className="tabular-nums">
                                    Top {chartSummary.play_count_limit}
                                </Badge>
                            </CardContent>
                        </Card>

                        {/* Card 2: Chart Period */}
                        <Card className="gap-3 py-4">
                            <CardHeader className="px-5 pb-0">
                                <CardDescription className="flex items-center justify-between text-xs">
                                    <span>Periodo Semanal</span>
                                    <Calendar className="size-3.5 text-muted-foreground" />
                                </CardDescription>
                                <CardTitle className="text-sm font-semibold sm:text-base">
                                    {formatShortDate(chartSummary.from)} →{' '}
                                    {formatShortDate(chartSummary.to)}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex items-center gap-1.5 px-5">
                                {chartSummary.synced ? (
                                    <Badge
                                        variant="secondary"
                                        className="border-emerald-500/25 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                    >
                                        <CheckCircle2 className="size-3" />
                                        Sincronizado
                                    </Badge>
                                ) : (
                                    <Badge variant="outline">
                                        <Clock className="size-3" />
                                        Pendiente
                                    </Badge>
                                )}
                            </CardContent>
                        </Card>

                        {/* Card 3: Total Weekly Scrobbles */}
                        <Card className="gap-3 py-4">
                            <CardHeader className="px-5 pb-0">
                                <CardDescription className="flex items-center justify-between text-xs">
                                    <span>Scrobbles de la Semana</span>
                                    <Headphones className="size-3.5 text-muted-foreground" />
                                </CardDescription>
                                <CardTitle className="text-2xl font-bold tabular-nums">
                                    {chartSummary.total_playcount.toLocaleString(
                                        'es-CO',
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="px-5">
                                <p className="text-xs text-muted-foreground">
                                    Reproducciones acumuladas en el top{' '}
                                    {tracks.length}
                                </p>
                            </CardContent>
                        </Card>

                        {/* Card 4: Synced Charts */}
                        <Card className="gap-3 py-4">
                            <CardHeader className="px-5 pb-0">
                                <CardDescription className="flex items-center justify-between text-xs">
                                    <span>Semanas Sincronizadas</span>
                                    <Layers className="size-3.5 text-muted-foreground" />
                                </CardDescription>
                                <CardTitle className="text-2xl font-bold tabular-nums">
                                    {chartSummary.total_charts_synced}{' '}
                                    <span className="text-sm font-normal text-muted-foreground">
                                        semanas
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="px-5">
                                <p className="text-xs text-muted-foreground">
                                    Histórico en{' '}
                                    <code className="font-mono text-foreground">
                                        last_fm_charts
                                    </code>
                                </p>
                            </CardContent>
                        </Card>
                    </section>

                    {/* Cabecera de Tabla + Filtros Interactivos */}
                    <section className="flex flex-col gap-4">
                        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                            <div>
                                <h2 className="flex items-center gap-2 text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                    <Trophy className="size-5 text-red-500" />
                                    Weekly Track Chart
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Ranking semanal de canciones con artista,
                                    álbum, etiquetas y proporción de
                                    reproducciones.
                                </p>
                            </div>

                            {/* Buscador rápido */}
                            <div className="relative w-full sm:w-72">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="search"
                                    value={searchQuery}
                                    onChange={(e) =>
                                        setSearchQuery(e.target.value)
                                    }
                                    placeholder="Filtrar track, artista, álbum o tag..."
                                    aria-label="Filtrar tabla de tracks"
                                    className="bg-card pr-8 pl-9 text-xs sm:text-sm"
                                />
                                {searchQuery && (
                                    <button
                                        type="button"
                                        onClick={() => setSearchQuery('')}
                                        aria-label="Limpiar búsqueda"
                                        className="absolute top-1/2 right-2.5 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Filtro de Tags */}
                        {allTags.length > 0 && (
                            <div
                                className="flex flex-wrap items-center gap-1.5"
                                role="group"
                                aria-label="Filtrar por etiqueta musical"
                            >
                                <Button
                                    type="button"
                                    variant={
                                        selectedTag === null
                                            ? 'default'
                                            : 'outline'
                                    }
                                    size="sm"
                                    onClick={() => setSelectedTag(null)}
                                    className="h-7 cursor-pointer rounded-full px-3 text-xs"
                                >
                                    Todos ({tracks.length})
                                </Button>
                                {allTags.map((tag) => {
                                    const isActive = selectedTag === tag;
                                    return (
                                        <Button
                                            key={tag}
                                            type="button"
                                            variant={
                                                isActive ? 'default' : 'outline'
                                            }
                                            size="sm"
                                            onClick={() =>
                                                setSelectedTag(
                                                    isActive ? null : tag,
                                                )
                                            }
                                            className="h-7 cursor-pointer rounded-full px-3 text-xs capitalize"
                                        >
                                            {tag}
                                        </Button>
                                    );
                                })}
                            </div>
                        )}

                        {/* Tabla Semántica Accesible */}
                        <div
                            tabIndex={0}
                            role="region"
                            aria-labelledby="weekly-tracks-table-caption"
                            className="w-full overflow-x-auto rounded-2xl border border-border bg-card shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
                        >
                            <table className="w-full border-collapse text-left text-sm">
                                <caption
                                    id="weekly-tracks-table-caption"
                                    className="sr-only"
                                >
                                    Ranking semanal de canciones de @
                                    {chartSummary.user} del{' '}
                                    {chartSummary.from} al {chartSummary.to}
                                </caption>

                                <thead className="sticky top-0 z-10 border-b border-border bg-card-header text-xs tracking-wider text-muted-foreground uppercase">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="w-16 px-4 py-3.5 font-semibold"
                                        >
                                            Rank
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3.5 font-semibold text-foreground"
                                        >
                                            Track
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3.5 font-semibold"
                                        >
                                            Artista
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3.5 font-semibold"
                                        >
                                            Álbum
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3.5 font-semibold"
                                        >
                                            Tags
                                        </th>
                                        <th
                                            scope="col"
                                            className="w-44 px-4 py-3.5 text-right font-semibold"
                                        >
                                            Playcount / Share
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-border text-foreground">
                                    {filteredTracks.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-6 py-10 text-center text-sm text-muted-foreground"
                                            >
                                                No se encontraron canciones que
                                                coincidan con el filtro actual.
                                            </td>
                                        </tr>
                                    ) : (
                                        filteredTracks.map((track) => {
                                            const sharePercent = Math.round(
                                                (track.playcount /
                                                    maxPlaycount) *
                                                    100,
                                            );
                                            const isTopThree = track.rank <= 3;

                                            return (
                                                <tr
                                                    key={track.id}
                                                    className="group transition-colors hover:bg-muted/60"
                                                >
                                                    {/* 1. Rank */}
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        <Badge
                                                            variant={
                                                                isTopThree
                                                                    ? 'default'
                                                                    : 'outline'
                                                            }
                                                            className={cn(
                                                                'font-mono tabular-nums',
                                                                isTopThree &&
                                                                    'border-red-500/30 bg-red-500/15 text-red-600 dark:text-red-400',
                                                            )}
                                                        >
                                                            #{track.rank}
                                                        </Badge>
                                                    </td>

                                                    {/* 2. Track (row header semántico) */}
                                                    <th
                                                        scope="row"
                                                        className="px-4 py-3.5 font-medium text-foreground"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <Music2 className="size-3.5 shrink-0 text-muted-foreground" />
                                                            <span className="font-semibold">
                                                                {
                                                                    track.track_name
                                                                }
                                                            </span>
                                                            {track.track_mbid && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="h-5 gap-1 px-1.5 font-mono text-[10px] text-muted-foreground"
                                                                    title={`MusicBrainz ID: ${track.track_mbid}`}
                                                                >
                                                                    <Fingerprint className="size-2.5" />
                                                                    MBID
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </th>

                                                    {/* 3. Artist */}
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        <div className="flex items-center gap-2.5">
                                                            <Avatar className="size-7 border border-border">
                                                                {track.artist_image_url && (
                                                                    <AvatarImage
                                                                        src={
                                                                            track.artist_image_url
                                                                        }
                                                                        alt={
                                                                            track.artist_name
                                                                        }
                                                                        className="object-cover"
                                                                    />
                                                                )}
                                                                <AvatarFallback className="text-[10px] font-semibold">
                                                                    {getInitials(
                                                                        track.artist_name,
                                                                    )}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            <span className="font-medium">
                                                                {
                                                                    track.artist_name
                                                                }
                                                            </span>
                                                        </div>
                                                    </td>

                                                    {/* 4. Album */}
                                                    <td className="px-4 py-3.5 text-muted-foreground">
                                                        <span className="line-clamp-1">
                                                            {track.album_name}
                                                        </span>
                                                    </td>

                                                    {/* 5. Tags */}
                                                    <td className="px-4 py-3.5">
                                                        <div className="flex flex-wrap gap-1">
                                                            {track.tags.map(
                                                                (tag) => (
                                                                    <Badge
                                                                        key={
                                                                            tag
                                                                        }
                                                                        variant="secondary"
                                                                        className="text-[11px] capitalize"
                                                                    >
                                                                        {tag}
                                                                    </Badge>
                                                                ),
                                                            )}
                                                        </div>
                                                    </td>

                                                    {/* 6. Playcount / Share */}
                                                    <td className="px-4 py-3.5 text-right whitespace-nowrap">
                                                        <div className="flex flex-col items-end gap-1.5">
                                                            <div className="flex items-baseline gap-1.5 font-mono text-sm font-semibold tabular-nums">
                                                                <span>
                                                                    {track.playcount.toLocaleString(
                                                                        'es-CO',
                                                                    )}
                                                                </span>
                                                                <span className="text-[11px] font-normal text-muted-foreground">
                                                                    plays
                                                                </span>
                                                            </div>
                                                            <div
                                                                className="h-1.5 w-28 overflow-hidden rounded-full bg-muted"
                                                                aria-label={`${sharePercent}% del máximo de reproducciones`}
                                                            >
                                                                <div
                                                                    className="h-full rounded-full bg-red-500 transition-all duration-300"
                                                                    style={{
                                                                        width: `${sharePercent}%`,
                                                                    }}
                                                                />
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </main>

                {/* Footer Minimalista */}
                <footer className="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-3 border-t border-border pt-6 text-xs text-muted-foreground sm:flex-row">
                    <p>
                        LastFM v3 • Datos servidos desde{' '}
                        <code className="font-mono text-foreground">
                            WelcomeController
                        </code>{' '}
                        con CSS{' '}
                        <code className="font-mono text-foreground">
                            light-dark()
                        </code>{' '}
                        nativo.
                    </p>
                    <div className="flex items-center gap-4">
                        <span>Inertia v3 + React 19</span>
                        <span>•</span>
                        <span>shadcn/ui</span>
                    </div>
                </footer>
            </div>
        </>
    );
}
