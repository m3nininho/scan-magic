import { Head, Link } from '@inertiajs/react';
import { AlertCircle, Images, Library, ScanLine, Sprout } from 'lucide-react';
import CollectionCardController from '@/actions/App/Http/Controllers/CollectionCardController';
import CollectionController from '@/actions/App/Http/Controllers/CollectionController';
import PendingScanController from '@/actions/App/Http/Controllers/PendingScanController';
import ScannerController from '@/actions/App/Http/Controllers/ScannerController';

type Summary = {
    totalCopies: number;
    differentCards: number;
    lands: number;
    creatures: number;
};
type RecentCard = { id: number; name: string; image_url: string | null };

export default function Dashboard({
    summary,
    pendingScans,
    recentCards,
}: {
    summary: Summary;
    pendingScans: number;
    recentCards: RecentCard[];
}) {
    const metrics = [
        { label: 'Total de cópias', value: summary.totalCopies, icon: Images },
        {
            label: 'Cartas diferentes',
            value: summary.differentCards,
            icon: Library,
        },
        { label: 'Terrenos', value: summary.lands, icon: Sprout },
        { label: 'Criaturas', value: summary.creatures, icon: ScanLine },
    ];
    return (
        <>
            <Head title="Início" />
            <main className="px-5 py-8 sm:px-8">
                <div className="mx-auto max-w-6xl space-y-8">
                    <header className="border-border flex flex-col gap-5 border-b pb-7 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-muted-foreground text-xs font-semibold tracking-widest uppercase">
                                Sua coleção
                            </p>
                            <h1 className="mt-2 text-4xl font-semibold tracking-tight">
                                Visão geral
                            </h1>
                        </div>
                        <Link
                            href={ScannerController.index.url()}
                            className="bg-primary text-primary-foreground inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium"
                        >
                            <ScanLine size={17} /> Adicionar cartas
                        </Link>
                    </header>
                    {pendingScans > 0 && (
                        <Link
                            href={PendingScanController.index.url()}
                            className="flex items-center justify-between gap-4 border border-amber-500/40 bg-amber-500/10 p-4"
                        >
                            <span className="flex items-center gap-3">
                                <AlertCircle size={20} />
                                <span>
                                    <strong className="block">
                                        {pendingScans}{' '}
                                        {pendingScans === 1
                                            ? 'scan precisa'
                                            : 'scans precisam'}{' '}
                                        de revisão
                                    </strong>
                                    <span className="text-muted-foreground text-sm">
                                        Confirme a carta correta para concluir.
                                    </span>
                                </span>
                            </span>
                            <span aria-hidden>→</span>
                        </Link>
                    )}
                    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {metrics.map(({ label, value, icon: Icon }) => (
                            <article
                                key={label}
                                className="border-border bg-card border p-5"
                            >
                                <div className="text-muted-foreground flex items-center justify-between text-sm">
                                    <span>{label}</span>
                                    <Icon size={18} />
                                </div>
                                <p className="mt-3 text-3xl font-semibold">
                                    {value}
                                </p>
                            </article>
                        ))}
                    </section>
                    <section className="grid gap-4 sm:grid-cols-2">
                        <Link
                            href={ScannerController.index.url()}
                            className="border-border bg-card hover:bg-muted border p-6"
                        >
                            <ScanLine />
                            <h2 className="mt-5 text-xl font-semibold">
                                Adicionar cartas
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Fotografe ou envie imagens para reconhecer
                                várias cartas.
                            </p>
                        </Link>
                        <Link
                            href={CollectionController.index.url()}
                            className="border-border bg-card hover:bg-muted border p-6"
                        >
                            <Library />
                            <h2 className="mt-5 text-xl font-semibold">
                                Explorar coleção
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Busque, filtre e ajuste as quantidades das suas
                                cartas.
                            </p>
                        </Link>
                    </section>
                    <section>
                        <div className="mb-4 flex items-end justify-between">
                            <div>
                                <p className="text-muted-foreground text-xs font-semibold tracking-widest uppercase">
                                    Adicionadas recentemente
                                </p>
                                <h2 className="mt-1 text-2xl font-semibold">
                                    Últimas cartas
                                </h2>
                            </div>
                            <Link
                                href={CollectionController.index.url()}
                                className="text-sm"
                            >
                                Ver coleção →
                            </Link>
                        </div>
                        {recentCards.length ? (
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                                {recentCards.map((card) => (
                                    <Link
                                        key={card.id}
                                        href={CollectionCardController.show.url(
                                            card.id,
                                        )}
                                        className="group"
                                    >
                                        <div className="bg-muted aspect-[5/7] overflow-hidden rounded">
                                            {card.image_url && (
                                                <img
                                                    src={card.image_url}
                                                    alt={card.name}
                                                    className="h-full w-full object-cover transition group-hover:scale-105"
                                                />
                                            )}
                                        </div>
                                        <p className="mt-2 truncate text-sm font-medium">
                                            {card.name}
                                        </p>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="border-border border p-8 text-center">
                                <p className="text-muted-foreground">
                                    Sua coleção ainda está vazia.
                                </p>
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </>
    );
}
