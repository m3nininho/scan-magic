import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { show, store } from '@/actions/App/Http/Controllers/DeckController';
import { Button } from '@/components/ui/button';

export type DeckDraft = {
    id?: number;
    name: string;
    format: string;
    strategy: string | null;
    commander_card_id: number | null;
    commander: string | null;
    cards: { card_id: number; name: string; quantity: number; role: string }[];
    total: number;
    target: number | null;
    complete: boolean;
    warnings: string[];
};

export function deckFormat(format: string) {
    return format === 'unspecified' ? 'Sem formato definido' : format;
}

export default function DeckSummary({
    deck,
    saveable = false,
}: {
    deck: DeckDraft;
    saveable?: boolean;
}) {
    const [saved, setSaved] = useState<DeckDraft>();
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string>();
    const current = saved ?? deck;

    async function save() {
        if (saving || saved) return;
        setSaving(true);
        setError(undefined);
        try {
            const token = document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.split('=')[1];
            const response = await fetch(store.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(token
                        ? { 'X-XSRF-TOKEN': decodeURIComponent(token) }
                        : {}),
                },
                body: JSON.stringify({
                    name: deck.name,
                    format: deck.format,
                    strategy: deck.strategy,
                    commander_card_id: deck.commander_card_id,
                    cards: deck.cards.map(({ card_id, quantity }) => ({
                        card_id,
                        quantity,
                    })),
                }),
            });
            if (response.status === 401 || response.status === 419) {
                throw new Error('Sua sessão expirou. Atualize a página.');
            }
            if (!response.ok) {
                const payload = await response.json();
                const errors = payload.errors as
                    | Record<string, string[]>
                    | undefined;
                throw new Error(
                    Object.values(errors ?? {}).flat()[0] ??
                        'Não foi possível salvar o deck. Tente novamente.',
                );
            }
            const payload: { deck: DeckDraft } = await response.json();
            setSaved(payload.deck);
        } catch (exception) {
            setError(
                exception instanceof Error && !(exception instanceof TypeError)
                    ? exception.message
                    : 'Não foi possível salvar. Verifique sua conexão.',
            );
        } finally {
            setSaving(false);
        }
    }

    return (
        <section className="space-y-4" aria-label={current.name}>
            <div>
                <h2 className="text-xl font-semibold">{current.name}</h2>
                <p className="text-muted-foreground text-sm capitalize">
                    {deckFormat(current.format)}
                </p>
            </div>
            {current.format === 'commander' && (
                <p className="text-sm">
                    <strong>Comandante:</strong>{' '}
                    {current.commander ?? 'Não definido'}
                </p>
            )}
            {current.strategy && <p className="text-sm">{current.strategy}</p>}
            <p className="font-semibold">
                {current.total}
                {current.target !== null ? `/${current.target}` : ''} cartas ·{' '}
                {current.complete ? 'Completo' : 'Parcial'}
            </p>
            {!current.complete && (
                <p className="text-muted-foreground text-sm">
                    {current.target === null
                        ? 'Defina um formato para verificar a composição completa.'
                        : 'Ainda faltam cartas ou um comandante válido para completar o deck com esta seleção da coleção.'}
                </p>
            )}
            {current.warnings.map((warning) => (
                <p key={warning} className="text-muted-foreground text-sm">
                    {warning}
                </p>
            ))}
            <ul
                className="divide-border max-h-80 divide-y overflow-y-auto text-sm"
                aria-label="Cartas do deck"
            >
                {current.cards.map((card) => (
                    <li
                        key={card.card_id}
                        className="flex items-start justify-between gap-4 py-2"
                    >
                        <span>
                            {card.name}
                            {card.role === 'commander' ? ' (comandante)' : ''}
                        </span>
                        <span className="shrink-0 tabular-nums">
                            ×{card.quantity}
                        </span>
                    </li>
                ))}
            </ul>
            {error && (
                <p role="alert" className="text-destructive text-sm">
                    {error}
                </p>
            )}
            {saveable && !saved && (
                <Button
                    type="button"
                    onClick={save}
                    disabled={saving || current.total === 0}
                >
                    {saving ? 'Salvando…' : 'Salvar deck'}
                </Button>
            )}
            {saved?.id && (
                <p role="status" className="text-sm">
                    Deck salvo.{' '}
                    <Link className="underline" href={show.url(saved.id)}>
                        Abrir deck
                    </Link>
                </p>
            )}
        </section>
    );
}
