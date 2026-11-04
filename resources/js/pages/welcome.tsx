import { Head } from '@inertiajs/react';
import { Camera, Disc3, Headphones, MapPin, Moon, Sparkles, Sun, TrendingUp } from 'lucide-react';
import * as React from 'react';

import {
    Carousel,
    type CarouselApi,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';

interface ArtistPhoto {
    src: string;
    caption: string;
}

interface Artist {
    id: string;
    name: string;
    genre: string;
    tags: string[];
    origin: string;
    activeYears: string;
    listeners: string;
    scrobbles: string;
    topTrack: string;
    image: string;
    gallery: ArtistPhoto[];
    isTopPriority?: boolean;
}

const ARTISTS: Artist[] = [
    {
        id: 'metallica',
        name: 'Metallica',
        genre: 'Thrash Metal / Heavy Metal',
        tags: ['Metal', 'Thrash Metal', 'Hard Rock'],
        origin: 'Los Ángeles, EE. UU.',
        activeYears: '1981 - Presente',
        listeners: '9.4M',
        scrobbles: '485.2M',
        topTrack: 'Enter Sandman',
        image: '/images/artists/metallica/1.jpg',
        isTopPriority: true,
        gallery: [
            {
                src: '/images/artists/metallica/1.jpg',
                caption: 'En vivo en The O2 Arena, Londres (WorldWired Tour)',
            },
            {
                src: '/images/artists/metallica/2.jpg',
                caption: 'Escenario central 360° en Londres, Inglaterra',
            },
            {
                src: '/images/artists/metallica/3.jpg',
                caption: 'Concierto masivo en el Ippodromo San Siro, Milán (2019)',
            },
            {
                src: '/images/artists/metallica/4.jpg',
                caption: 'Presentación clásica en vivo en Londres (2003)',
            },
        ],
    },
    {
        id: 'the-beatles',
        name: 'The Beatles',
        genre: 'Classic Rock / Psychedelic Rock',
        tags: ['Rock', 'Classic Rock', '60s'],
        origin: 'Liverpool, Reino Unido',
        activeYears: '1960 - 1970',
        listeners: '8.1M',
        scrobbles: '392.8M',
        topTrack: 'Here Comes the Sun',
        image: '/images/artists/the-beatles/1.jpg',
        gallery: [
            {
                src: '/images/artists/the-beatles/1.jpg',
                caption: 'Llegada histórica al Aeropuerto JFK, Nueva York (1964)',
            },
            {
                src: '/images/artists/the-beatles/2.jpg',
                caption: 'Retrato promocional oficial del cuarteto de Liverpool (1965)',
            },
            {
                src: '/images/artists/the-beatles/3.jpg',
                caption: 'En vivo en el Cleveland Public Hall (Septiembre 1964)',
            },
            {
                src: '/images/artists/the-beatles/4.jpg',
                caption: 'Rueda de prensa en el Hotel George Washington (1964)',
            },
        ],
    },
    {
        id: 'babymonster',
        name: 'BABYMONSTER',
        genre: 'K-Pop / Hip-Hop / Dance-Pop',
        tags: ['K-Pop', 'Hip-Hop', 'Dance'],
        origin: 'Seúl, Corea del Sur',
        activeYears: '2023 - Presente',
        listeners: '1.8M',
        scrobbles: '42.6M',
        topTrack: 'SHEESH',
        image: '/images/artists/babymonster/1.png',
        gallery: [
            {
                src: '/images/artists/babymonster/1.png',
                caption: 'Promoción oficial del álbum DRIP (Noviembre 2024)',
            },
            {
                src: '/images/artists/babymonster/2.png',
                caption: 'Aparición especial en SBS Radio, Seúl (Abril 2024)',
            },
            {
                src: '/images/artists/babymonster/3.png',
                caption: 'Sesión de prensa en SBS Radio para FOREVER (Julio 2024)',
            },
            {
                src: '/images/artists/babymonster/4.png',
                caption: 'Presentación grupal en evento oficial (Septiembre 2024)',
            },
        ],
    },
];

/**
 * Implementación del Two-State Toggle según `dark-mode-toggle` y `dark-mode`:
 * - Estado 1: System default (sin valor en localStorage, <meta name="color-scheme" content="light dark">)
 * - Estado 2: Override explícito ('light' o 'dark' en localStorage y en <meta name="color-scheme">)
 * - Si al alternar el esquema objetivo coincide con el del sistema operativo, borra localStorage para volver a System.
 */
function ColorSchemeToggle() {
    const [storedOverride, setStoredOverride] = React.useState<'light' | 'dark' | null>(() => {
        if (typeof window === 'undefined') return null;
        try {
            const val = localStorage.getItem('color-scheme');
            return val === 'light' || val === 'dark' ? val : null;
        } catch {
            return null;
        }
    });

    const [systemScheme, setSystemScheme] = React.useState<'light' | 'dark'>(() => {
        if (typeof window === 'undefined') return 'dark';
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    });

    React.useEffect(() => {
        const mql = window.matchMedia('(prefers-color-scheme: dark)');
        const onSystemChange = (e: MediaQueryListEvent) => {
            // MANDATORY según dark-mode-toggle: NUNCA borrar localStorage cuando el OS cambie,
            // solo actualizar la lectura del sistema para resolver el tema cuando no hay override.
            setSystemScheme(e.matches ? 'dark' : 'light');
        };
        mql.addEventListener('change', onSystemChange);

        const onStorage = (e: StorageEvent) => {
            if (e.key === 'color-scheme') {
                const next = e.newValue === 'light' || e.newValue === 'dark' ? e.newValue : null;
                setStoredOverride(next);
                const meta = document.querySelector('meta[name="color-scheme"]');
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
        const targetScheme: 'light' | 'dark' = renderedScheme === 'dark' ? 'light' : 'dark';
        const currentSys: 'light' | 'dark' = window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
        const meta = document.querySelector('meta[name="color-scheme"]');

        if (targetScheme === currentSys) {
            // El usuario deshizo su ajuste manual -> vuelve a seguir al sistema ("light dark")
            try {
                localStorage.removeItem('color-scheme');
            } catch {}
            setStoredOverride(null);
            if (meta) meta.setAttribute('content', 'light dark');
        } else {
            // Difiere del sistema -> guarda el override literal ("light" o "dark")
            try {
                localStorage.setItem('color-scheme', targetScheme);
            } catch {}
            setStoredOverride(targetScheme);
            if (meta) meta.setAttribute('content', targetScheme);
        }
    };

    return (
        <button
            type="button"
            onClick={handleToggle}
            className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium bg-card border border-border text-foreground hover:bg-muted transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
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
            <span className="text-[10px] px-1.5 py-0.2 rounded bg-muted text-muted-foreground font-mono">
                {storedOverride ? 'Fijo' : 'Auto'}
            </span>
        </button>
    );
}

function ArtistGalleryHoverCard({ artist }: { artist: Artist }) {
    const [api, setApi] = React.useState<CarouselApi>();
    const [current, setCurrent] = React.useState(0);

    React.useEffect(() => {
        if (!api) return;

        setCurrent(api.selectedScrollSnap());
        api.on('select', () => {
            setCurrent(api.selectedScrollSnap());
        });
    }, [api]);

    return (
        <HoverCard openDelay={120} closeDelay={220}>
            <HoverCardTrigger asChild>
                <button
                    type="button"
                    className="flex items-center gap-3.5 text-left group/trigger focus:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-xl p-1 -m-1 cursor-pointer"
                >
                    <div className="relative size-14 shrink-0 overflow-hidden rounded-xl border border-border shadow-sm bg-muted">
                        <img
                            src={artist.image}
                            alt={`Fotografía oficial de ${artist.name}`}
                            width={56}
                            height={56}
                            fetchPriority={artist.isTopPriority ? 'high' : 'auto'}
                            loading={artist.isTopPriority ? 'eager' : 'lazy'}
                            decoding="async"
                            className="size-full object-cover object-center group-hover/trigger:scale-105 transition-transform duration-300"
                        />
                        <span className="absolute bottom-1 right-1 inline-flex items-center justify-center rounded-md bg-black/75 px-1 py-0.5 text-[9px] font-semibold text-white backdrop-blur-xs border border-white/10">
                            4
                        </span>
                    </div>
                    <div>
                        <span className="flex items-center gap-1.5 text-base font-bold text-foreground group-hover/trigger:text-red-500 transition-colors">
                            {artist.name}
                            <Camera className="size-3.5 text-muted-foreground group-hover/trigger:text-red-500 transition-colors" />
                        </span>
                        <span className="text-xs text-muted-foreground font-normal block">
                            {artist.genre}
                        </span>
                    </div>
                </button>
            </HoverCardTrigger>

            <HoverCardContent
                side="right"
                align="start"
                sideOffset={14}
                className="w-88 sm:w-96 p-3.5 bg-popover/95 backdrop-blur-md border-border shadow-2xl rounded-2xl"
            >
                {/* Header del Popup */}
                <div className="flex items-center justify-between gap-2 mb-2.5">
                    <div>
                        <h3 className="text-sm font-bold text-popover-foreground leading-tight">
                            {artist.name}
                        </h3>
                        <p className="text-[11px] text-muted-foreground">
                            {artist.origin} • {artist.activeYears}
                        </p>
                    </div>
                    <span className="inline-flex items-center gap-1 rounded-full bg-muted border border-border px-2.5 py-0.5 text-[11px] font-mono font-medium text-foreground">
                        {current + 1} / {artist.gallery.length}
                    </span>
                </div>

                {/* Carrusel shadcn/ui con Embla */}
                <Carousel
                    setApi={setApi}
                    opts={{
                        align: 'start',
                        loop: true,
                    }}
                    className="w-full"
                >
                    <CarouselContent>
                        {artist.gallery.map((photo, index) => (
                            <CarouselItem key={photo.src}>
                                <div className="relative aspect-16/10 w-full overflow-hidden rounded-xl border border-border bg-muted">
                                    <img
                                        src={photo.src}
                                        alt={`${artist.name} — Foto ${index + 1}: ${photo.caption}`}
                                        width={360}
                                        height={225}
                                        loading="eager"
                                        decoding="async"
                                        className="size-full object-cover object-center"
                                    />
                                    <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 via-black/45 to-transparent p-2.5 pt-6">
                                        <p className="text-[11px] text-white leading-snug line-clamp-2">
                                            {photo.caption}
                                        </p>
                                    </div>
                                </div>
                            </CarouselItem>
                        ))}
                    </CarouselContent>
                    <CarouselPrevious />
                    <CarouselNext />
                </Carousel>

                {/* Indicadores inferiores (Dots) + Canción #1 */}
                <div className="mt-3 pt-2.5 border-t border-border flex items-center justify-between gap-2">
                    <div className="flex items-center gap-1.5" role="tablist" aria-label="Selector de foto">
                        {artist.gallery.map((photo, idx) => (
                            <button
                                key={photo.src}
                                type="button"
                                role="tab"
                                aria-selected={current === idx}
                                aria-label={`Ir a foto ${idx + 1}`}
                                onClick={() => api?.scrollTo(idx)}
                                className={`h-1.5 rounded-full transition-all cursor-pointer ${
                                    current === idx
                                        ? 'w-5 bg-red-500'
                                        : 'w-1.5 bg-muted-foreground/40 hover:bg-muted-foreground'
                                }`}
                            />
                        ))}
                    </div>
                    <span className="text-[11px] text-muted-foreground truncate">
                        Top Track: <strong className="text-foreground">{artist.topTrack}</strong>
                    </span>
                </div>
            </HoverCardContent>
        </HoverCard>
    );
}

export default function Welcome() {
    return (
        <>
            <Head title="Artistas Destacados - LastFM v3" />

            <div className="min-h-dvh bg-background text-foreground antialiased selection:bg-red-500/30 flex flex-col justify-between p-4 sm:p-6 lg:p-10 font-sans transition-colors duration-200">
                
                {/* Header Superior Limpio (Cero Auth / Cero Login) */}
                <header className="max-w-5xl mx-auto w-full flex flex-wrap items-center justify-between gap-4 pb-8 border-b border-border">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-xl bg-gradient-to-br from-red-600 to-red-900 flex items-center justify-center text-white shadow-md border border-red-500/30">
                            <Disc3 className="size-5 animate-[spin_6s_linear_infinite]" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-base font-bold text-foreground tracking-tight">LastFM v3</h1>
                                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-500/10 text-red-600 border border-red-500/25">
                                    <Sparkles className="size-2.5" />
                                    Zero-Login
                                </span>
                            </div>
                            <p className="text-xs text-muted-foreground">Directorio Musical & Métricas de Reproducción</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <span className="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-card border border-border text-muted-foreground">
                            <span className="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            3 Artistas • 12 Fotos
                        </span>
                        <ColorSchemeToggle />
                    </div>
                </header>

                {/* Contenido Principal con Tabla Semántica y Responsive */}
                <main className="max-w-5xl mx-auto w-full my-8 flex-1 flex flex-col justify-center">
                    <div className="mb-6 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                        <div>
                            <h2 className="text-2xl font-bold text-foreground tracking-tight flex items-center gap-2.5">
                                Artistas Destacados
                            </h2>
                            <p className="text-sm text-muted-foreground mt-1">
                                Pasa el cursor sobre cualquier artista para explorar su galería interactiva de 4 fotografías (HoverCard + Carousel de shadcn/ui).
                            </p>
                        </div>
                        <div className="flex items-center gap-3 text-xs text-muted-foreground bg-card px-3 py-1.5 rounded-lg border border-border">
                            <TrendingUp className="size-4 text-emerald-500" />
                            <span>Métricas acumuladas de Last.fm</span>
                        </div>
                    </div>

                    {/* Wrapper de la Tabla según 'responsive-table' (tabindex="0", role="region", aria-labelledby) */}
                    <div 
                        tabIndex={0} 
                        role="region" 
                        aria-labelledby="artists-table-caption" 
                        className="w-full overflow-x-auto rounded-2xl border border-border bg-card shadow-lg focus:outline-none focus:ring-2 focus:ring-ring [container-type:inline-size]"
                    >
                        <table className="w-full text-left text-sm border-collapse">
                            <caption id="artists-table-caption" className="sr-only">
                                Directorio de Artistas Destacados: Metallica, The Beatles y BABYMONSTER
                            </caption>
                            
                            {/* Cabecera Sticky */}
                            <thead className="bg-card-header text-muted-foreground text-xs uppercase tracking-wider sticky top-0 z-10 border-b border-border">
                                <tr>
                                    <th scope="col" className="px-5 py-4 font-semibold text-foreground">
                                        Artista (Hover Galería)
                                    </th>
                                    <th scope="col" className="px-5 py-4 font-semibold">
                                        Género & Etiquetas
                                    </th>
                                    <th scope="col" className="px-5 py-4 font-semibold">
                                        Origen & Época
                                    </th>
                                    <th scope="col" className="px-5 py-4 font-semibold">
                                        Canción #1
                                    </th>
                                    <th scope="col" className="px-5 py-4 font-semibold text-right">
                                        Oyentes / Scrobbles
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border text-foreground">
                                {ARTISTS.map((artist) => (
                                    <tr 
                                        key={artist.id}
                                        className="hover:bg-muted/60 transition-colors group"
                                    >
                                        {/* Row header semántico según guía (th con scope="row") */}
                                        <th 
                                            scope="row" 
                                            className="px-5 py-4 font-medium text-foreground whitespace-nowrap"
                                        >
                                            <ArtistGalleryHoverCard artist={artist} />
                                        </th>

                                        {/* Género y Tags */}
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-1.5 max-w-xs">
                                                {artist.tags.map((tag) => (
                                                    <span 
                                                        key={tag} 
                                                        className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-muted text-foreground border border-border transition-colors"
                                                    >
                                                        {tag}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>

                                        {/* Origen & Periodo */}
                                        <td className="px-5 py-4 text-foreground whitespace-nowrap">
                                            <div className="flex items-center gap-1.5 text-xs">
                                                <MapPin className="size-3.5 text-muted-foreground shrink-0" />
                                                <span>{artist.origin}</span>
                                            </div>
                                            <div className="text-[11px] text-muted-foreground mt-0.5 pl-5">
                                                {artist.activeYears}
                                            </div>
                                        </td>

                                        {/* Top Track */}
                                        <td className="px-5 py-4 whitespace-nowrap">
                                            <div className="inline-flex items-center gap-2 px-2.5 py-1 rounded-lg bg-muted border border-border text-xs font-medium text-foreground">
                                                <span className="size-1.5 rounded-full bg-red-500"></span>
                                                <span>{artist.topTrack}</span>
                                            </div>
                                        </td>

                                        {/* Oyentes / Scrobbles */}
                                        <td className="px-5 py-4 text-right whitespace-nowrap">
                                            <div className="flex items-center justify-end gap-1.5 text-sm font-semibold text-foreground">
                                                <Headphones className="size-3.5 text-muted-foreground" />
                                                <span>{artist.listeners}</span>
                                            </div>
                                            <div className="text-[11px] text-muted-foreground mt-0.5">
                                                {artist.scrobbles} reproducciones
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </main>

                {/* Footer Minimalista */}
                <footer className="max-w-5xl mx-auto w-full pt-6 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground">
                    <p>LastFM v3 • Arquitectura Zero-Login con CSS <code className="font-mono text-foreground">light-dark()</code> nativo.</p>
                    <div className="flex items-center gap-4">
                        <span>shadcn/ui HoverCard + Carousel</span>
                        <span>•</span>
                        <span>Dark Mode Guide</span>
                    </div>
                </footer>

            </div>
        </>
    );
}
