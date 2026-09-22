import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Minus, Plus } from 'lucide-react';
import { FormEvent } from 'react';
import CollectionCardController from '@/actions/App/Http/Controllers/CollectionCardController';
import CollectionController from '@/actions/App/Http/Controllers/CollectionController';

type Color = { id: number; name: string; code: string };
type CardType = { id: number; name: string; category: string };
type Legality = {
    id: number;
    legality: string;
    format: { id: number; name: string };
};
type CardFace = {
    name?: string;
    mana_cost?: string;
    type_line?: string;
    oracle_text?: string;
    power?: string;
    toughness?: string;
    loyalty?: string;
    image_uris?: { normal?: string; large?: string };
};
type Card = {
    id: number;
    name: string;
    layout?: string;
    mana_cost?: string;
    cmc?: number;
    type?: string;
    rarity?: string;
    set_code?: string;
    set_name?: string;
    text?: string;
    flavor?: string;
    artist?: string;
    number?: string;
    power?: string;
    toughness?: string;
    loyalty?: string;
    image_url?: string;
    image_uris?: { normal?: string; large?: string };
    card_faces?: CardFace[];
    colors: Color[];
    color_identity: Color[];
    card_types: CardType[];
    legalities: Legality[];
};

type Props = { card: Card; quantity: number };

export default function CardDetails({ card, quantity }: Props) {
    const form = useForm({ quantity });
    const faces = card.card_faces ?? [];
    const mainImage =
        card.image_uris?.large ??
        card.image_uris?.normal ??
        card.image_url ??
        faces[0]?.image_uris?.large ??
        faces[0]?.image_uris?.normal;
    const subtypes = card.card_types
        .filter((type) => type.category === 'subtype')
        .map((type) => type.name);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(CollectionCardController.update.url(card.id), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={card.name} />
            <main className="bg-background text-foreground min-h-full px-4 py-6 sm:px-8 sm:py-10">
                <div className="mx-auto max-w-6xl">
                    <Link
                        href={CollectionController.index.url()}
                        className="text-muted-foreground hover:text-foreground mb-7 inline-flex items-center gap-2 text-sm"
                    >
                        <ArrowLeft size={16} /> Voltar para a coleção
                    </Link>

                    <div className="grid gap-8 lg:grid-cols-[minmax(260px,380px)_1fr]">
                        <section>
                            {mainImage ? (
                                <img
                                    src={mainImage}
                                    alt={card.name}
                                    className="mx-auto w-full max-w-sm rounded-[4.5%] shadow-2xl"
                                />
                            ) : (
                                <div className="bg-muted text-muted-foreground flex aspect-[5/7] items-center justify-center rounded-xl">
                                    Imagem indisponível
                                </div>
                            )}
                            {faces.length > 1 && (
                                <div className="mt-4 grid grid-cols-2 gap-3">
                                    {faces.slice(1).map((face) => {
                                        const image =
                                            face.image_uris?.large ??
                                            face.image_uris?.normal;

                                        return image ? (
                                            <img
                                                key={face.name}
                                                src={image}
                                                alt={face.name ?? card.name}
                                                className="rounded-[4.5%]"
                                            />
                                        ) : null;
                                    })}
                                </div>
                            )}
                        </section>

                        <section className="flex flex-col gap-7">
                            <header className="border-border border-b pb-6">
                                <p className="text-muted-foreground text-sm font-medium tracking-wide uppercase">
                                    {card.set_name ?? 'Edição não informada'}
                                </p>
                                <div className="mt-2 flex flex-wrap items-start justify-between gap-3">
                                    <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                                        {card.name}
                                    </h1>
                                    {card.mana_cost && (
                                        <span className="bg-muted rounded-md px-3 py-1.5 font-mono text-sm">
                                            {card.mana_cost}
                                        </span>
                                    )}
                                </div>
                                <p className="text-muted-foreground mt-3">
                                    {card.type}
                                </p>
                                {subtypes.length > 0 && (
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        Subtipos: {subtypes.join(', ')}
                                    </p>
                                )}
                            </header>

                            <form
                                onSubmit={submit}
                                className="border-border bg-card flex flex-wrap items-center justify-between gap-4 border p-4"
                            >
                                <div>
                                    <p className="font-medium">
                                        Quantidade na coleção
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        Defina zero para remover a carta da
                                        coleção.
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        aria-label="Diminuir quantidade"
                                        onClick={() =>
                                            form.setData(
                                                'quantity',
                                                Math.max(
                                                    0,
                                                    form.data.quantity - 1,
                                                ),
                                            )
                                        }
                                        className="border-border hover:bg-muted flex size-10 items-center justify-center border"
                                    >
                                        <Minus size={17} />
                                    </button>
                                    <input
                                        aria-label="Quantidade"
                                        type="number"
                                        min={0}
                                        max={9999}
                                        value={form.data.quantity}
                                        onChange={(event) =>
                                            form.setData(
                                                'quantity',
                                                Number(event.target.value),
                                            )
                                        }
                                        className="border-input h-10 w-20 border bg-transparent text-center"
                                    />
                                    <button
                                        type="button"
                                        aria-label="Aumentar quantidade"
                                        onClick={() =>
                                            form.setData(
                                                'quantity',
                                                Math.min(
                                                    9999,
                                                    form.data.quantity + 1,
                                                ),
                                            )
                                        }
                                        className="border-border hover:bg-muted flex size-10 items-center justify-center border"
                                    >
                                        <Plus size={17} />
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="bg-primary text-primary-foreground h-10 px-4 text-sm font-medium disabled:opacity-50"
                                    >
                                        Salvar
                                    </button>
                                </div>
                                {form.errors.quantity && (
                                    <p className="text-destructive w-full text-sm">
                                        {form.errors.quantity}
                                    </p>
                                )}
                            </form>

                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Detail
                                    label="Valor de mana"
                                    value={card.cmc}
                                />
                                <Detail label="Raridade" value={card.rarity} />
                                <Detail label="Edição" value={card.set_name} />
                                <Detail label="Número" value={card.number} />
                                <Detail label="Artista" value={card.artist} />
                                <Detail
                                    label="Cores"
                                    value={
                                        card.colors
                                            .map((color) => color.name)
                                            .join(', ') || 'Incolor'
                                    }
                                />
                                <Detail
                                    label="Identidade de cor"
                                    value={
                                        card.color_identity
                                            .map((color) => color.name)
                                            .join(', ') || 'Incolor'
                                    }
                                />
                                {card.power !== null &&
                                    card.power !== undefined && (
                                        <Detail
                                            label="Poder / Resistência"
                                            value={`${card.power ?? '—'} / ${card.toughness ?? '—'}`}
                                        />
                                    )}
                                {card.loyalty && (
                                    <Detail
                                        label="Lealdade"
                                        value={card.loyalty}
                                    />
                                )}
                            </dl>

                            {(card.text || faces.length > 0) && (
                                <div className="border-border border-t pt-6">
                                    <h2 className="text-lg font-semibold">
                                        Texto da carta
                                    </h2>
                                    {card.text && (
                                        <p className="mt-3 leading-relaxed whitespace-pre-line">
                                            {card.text}
                                        </p>
                                    )}
                                    {faces.map((face) => (
                                        <div key={face.name} className="mt-5">
                                            <h3 className="font-medium">
                                                {face.name}
                                            </h3>
                                            <p className="text-muted-foreground mt-1 text-sm leading-relaxed whitespace-pre-line">
                                                {face.oracle_text}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {card.legalities.length > 0 && (
                                <div className="border-border border-t pt-6">
                                    <h2 className="text-lg font-semibold">
                                        Legalidade
                                    </h2>
                                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                        {card.legalities.map((legality) => (
                                            <div
                                                key={legality.id}
                                                className="bg-muted/50 flex justify-between gap-3 px-3 py-2 text-sm"
                                            >
                                                <span>
                                                    {legality.format.name}
                                                </span>
                                                <span className="text-muted-foreground capitalize">
                                                    {legality.legality}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </section>
                    </div>
                </div>
            </main>
        </>
    );
}

function Detail({
    label,
    value,
}: {
    label: string;
    value?: string | number | null;
}) {
    if (value === null || value === undefined || value === '') return null;

    return (
        <div className="border-border border-b pb-3">
            <dt className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                {label}
            </dt>
            <dd className="mt-1">{value}</dd>
        </div>
    );
}
