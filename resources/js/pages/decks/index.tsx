import { Head, Link } from '@inertiajs/react';
import { index as chat } from '@/actions/App/Http/Controllers/DeckChatController';
import { show } from '@/actions/App/Http/Controllers/DeckController';
import { deckFormat } from '@/components/deck-summary';
import type { DeckDraft } from '@/components/deck-summary';

type SavedDeck = Omit<DeckDraft, 'cards' | 'warnings'> & { id: number };

export default function Decks({
    decks,
}: {
    decks: {
        data: SavedDeck[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    return (
        <>
            <Head title="Meus decks" />
            <main className="px-5 py-8 sm:px-8">
                <div className="mx-auto max-w-7xl space-y-8">
                    <header className="border-border flex flex-wrap items-center justify-between gap-4 border-b pb-7">
                        <h1 className="text-4xl font-semibold tracking-tight">
                            Meus decks
                        </h1>
                        <Link href={chat.url()} className="text-sm underline">
                            Criar no Chat Magic
                        </Link>
                    </header>
                    {decks.data.length === 0 && (
                        <p className="text-muted-foreground">
                            Você ainda não salvou nenhum deck. Comece pelo Chat
                            Magic.
                        </p>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {decks.data.map((deck) => (
                            <Link
                                key={deck.id}
                                href={show.url(deck.id)}
                                className="border-border bg-card hover:bg-accent space-y-3 rounded-lg border p-5"
                            >
                                <h2 className="text-xl font-semibold">
                                    {deck.name}
                                </h2>
                                <p className="text-muted-foreground text-sm capitalize">
                                    {deckFormat(deck.format)}
                                </p>
                                {deck.format === 'commander' && (
                                    <p className="text-sm">
                                        Comandante:{' '}
                                        {deck.commander ?? 'Não definido'}
                                    </p>
                                )}
                                <p className="text-sm">
                                    {deck.total}
                                    {deck.target !== null
                                        ? `/${deck.target}`
                                        : ''}{' '}
                                    cartas ·{' '}
                                    {deck.complete ? 'Completo' : 'Parcial'}
                                </p>
                            </Link>
                        ))}
                    </div>
                    <nav
                        aria-label="Paginação dos decks"
                        className="flex justify-between text-sm"
                    >
                        {decks.prev_page_url ? (
                            <Link href={decks.prev_page_url}>Anterior</Link>
                        ) : (
                            <span />
                        )}
                        {decks.next_page_url && (
                            <Link href={decks.next_page_url}>Próxima</Link>
                        )}
                    </nav>
                </div>
            </main>
        </>
    );
}
