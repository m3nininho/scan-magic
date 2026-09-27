import { Head, Link } from '@inertiajs/react';
import { index } from '@/actions/App/Http/Controllers/DeckController';
import DeckSummary from '@/components/deck-summary';
import type { DeckDraft } from '@/components/deck-summary';

export default function ShowDeck({ deck }: { deck: DeckDraft }) {
    return (
        <>
            <Head title={deck.name} />
            <main className="px-5 py-8 sm:px-8">
                <div className="mx-auto max-w-4xl space-y-6">
                    <Link href={index.url()} className="text-sm underline">
                        Voltar para Meus decks
                    </Link>
                    <div className="border-border bg-card rounded-lg border p-5 sm:p-8">
                        <DeckSummary deck={deck} />
                    </div>
                </div>
            </main>
        </>
    );
}
