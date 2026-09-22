import { Head, Link, router } from '@inertiajs/react';
import { Filter, Library, ScanLine, Search } from 'lucide-react';
import { FormEvent, useState } from 'react';
import CollectionCardController from '@/actions/App/Http/Controllers/CollectionCardController';
import CollectionController from '@/actions/App/Http/Controllers/CollectionController';
import ScannerController from '@/actions/App/Http/Controllers/ScannerController';

type Card = {
    id: number;
    name: string;
    image_url: string | null;
    set_name: string | null;
    rarity: string | null;
    pivot: { quantity: number };
};
type Page<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Summary = Record<
    | 'totalCopies'
    | 'differentCards'
    | 'lands'
    | 'creatures'
    | 'artifacts'
    | 'enchantments'
    | 'instants'
    | 'sorceries'
    | 'planeswalkers',
    number
>;
type Filters = {
    search?: string;
    type?: string;
    color?: string;
    rarity?: string;
    set?: string;
};
type Options = {
    types: string[];
    colors: { value: string; label: string }[];
    rarities: string[];
    sets: { value: string; label: string }[];
};

export default function Collection({
    cards,
    summary,
    filters,
    filterOptions,
}: {
    cards: Page<Card>;
    summary: Summary;
    filters: Filters;
    filterOptions: Options;
}) {
    const [values, setValues] = useState(filters);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(CollectionController.index.url(), values, {
            preserveState: true,
            replace: true,
        });
    };
    const stats: [string, number][] = [
        ['Cópias', summary.totalCopies],
        ['Diferentes', summary.differentCards],
        ['Terrenos', summary.lands],
        ['Criaturas', summary.creatures],
        ['Artefatos', summary.artifacts],
        ['Encantamentos', summary.enchantments],
        ['Instantâneas', summary.instants],
        ['Feitiços', summary.sorceries],
        ['Planeswalkers', summary.planeswalkers],
    ];
    const selects: [keyof Filters, string, string[]][] = [
        ['type', 'Tipo', filterOptions.types],
        ['rarity', 'Raridade', filterOptions.rarities],
    ];
    return (
        <>
            <Head title="Minha coleção" />
            <main className="px-5 py-8 sm:px-8">
                <div className="mx-auto max-w-7xl space-y-8">
                    <header className="border-border flex flex-col gap-5 border-b pb-7 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-muted-foreground flex items-center gap-2 text-xs font-semibold tracking-widest uppercase">
                                <Library size={15} /> Biblioteca pessoal
                            </p>
                            <h1 className="mt-2 text-4xl font-semibold tracking-tight">
                                Minha coleção
                            </h1>
                        </div>
                        <Link
                            href={ScannerController.index.url()}
                            className="bg-primary text-primary-foreground inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium"
                        >
                            <ScanLine size={17} /> Adicionar cartas
                        </Link>
                    </header>
                    <section className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-9">
                        {stats.map(([label, value]) => (
                            <article
                                key={label}
                                className="border-border bg-card border p-4"
                            >
                                <p className="text-muted-foreground text-xs">
                                    {label}
                                </p>
                                <p className="mt-2 text-2xl font-semibold">
                                    {value}
                                </p>
                            </article>
                        ))}
                    </section>
                    <form
                        onSubmit={submit}
                        className="border-border bg-card grid gap-3 border p-4 md:grid-cols-3 xl:grid-cols-6"
                    >
                        <label className="relative md:col-span-2">
                            <Search
                                className="text-muted-foreground absolute top-2.5 left-3"
                                size={17}
                            />
                            <input
                                value={values.search ?? ''}
                                onChange={(e) =>
                                    setValues({
                                        ...values,
                                        search: e.target.value,
                                    })
                                }
                                placeholder="Buscar por nome"
                                className="border-input bg-background w-full border py-2 pr-3 pl-10 text-sm"
                            />
                        </label>
                        {selects.map(([key, label, options]) => (
                            <select
                                key={key}
                                value={values[key] ?? ''}
                                onChange={(e) =>
                                    setValues({
                                        ...values,
                                        [key]: e.target.value,
                                    })
                                }
                                className="border-input bg-background border px-3 py-2 text-sm"
                            >
                                <option value="">{label}: todos</option>
                                {options.map((value) => (
                                    <option key={value}>{value}</option>
                                ))}
                            </select>
                        ))}
                        <select
                            value={values.color ?? ''}
                            onChange={(e) =>
                                setValues({ ...values, color: e.target.value })
                            }
                            className="border-input bg-background border px-3 py-2 text-sm"
                        >
                            <option value="">Cor: todas</option>
                            {filterOptions.colors.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <select
                            value={values.set ?? ''}
                            onChange={(e) =>
                                setValues({ ...values, set: e.target.value })
                            }
                            className="border-input bg-background border px-3 py-2 text-sm"
                        >
                            <option value="">Coleção: todas</option>
                            {filterOptions.sets.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <button className="bg-primary text-primary-foreground inline-flex items-center justify-center gap-2 px-4 py-2 text-sm">
                            <Filter size={16} /> Filtrar
                        </button>
                    </form>
                    {cards.data.length ? (
                        <section className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                            {cards.data.map((card) => (
                                <Link
                                    key={card.id}
                                    href={CollectionCardController.show.url(
                                        card.id,
                                    )}
                                    className="group"
                                >
                                    <div className="bg-muted relative aspect-[5/7] overflow-hidden rounded">
                                        {card.image_url ? (
                                            <img
                                                src={card.image_url}
                                                alt={card.name}
                                                className="h-full w-full object-cover transition group-hover:scale-105"
                                            />
                                        ) : (
                                            <div className="flex h-full items-center justify-center p-4 text-center text-sm">
                                                {card.name}
                                            </div>
                                        )}
                                        <span className="bg-background/90 absolute right-2 bottom-2 rounded-full px-2 py-1 text-xs font-bold">
                                            ×{card.pivot.quantity}
                                        </span>
                                    </div>
                                    <h2 className="mt-2 truncate text-sm font-semibold">
                                        {card.name}
                                    </h2>
                                    <p className="text-muted-foreground truncate text-xs">
                                        {card.set_name ?? card.rarity}
                                    </p>
                                </Link>
                            ))}
                        </section>
                    ) : (
                        <section className="border-border border p-10 text-center">
                            <h2 className="font-semibold">
                                Nenhuma carta encontrada
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Ajuste os filtros ou adicione cartas à coleção.
                            </p>
                        </section>
                    )}
                    {cards.last_page > 1 && (
                        <nav className="flex justify-between text-sm">
                            {cards.prev_page_url ? (
                                <Link href={cards.prev_page_url}>
                                    ← Anterior
                                </Link>
                            ) : (
                                <span />
                            )}
                            <span>
                                Página {cards.current_page} de {cards.last_page}
                            </span>
                            {cards.next_page_url ? (
                                <Link href={cards.next_page_url}>
                                    Próxima →
                                </Link>
                            ) : (
                                <span />
                            )}
                        </nav>
                    )}
                </div>
            </main>
        </>
    );
}
