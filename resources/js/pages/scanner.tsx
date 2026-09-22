import { Head } from '@inertiajs/react';
import { Check, ImagePlus, LoaderCircle, Search, Upload } from 'lucide-react';
import { ChangeEvent, FormEvent, useMemo, useState } from 'react';
import ScannerController from '@/actions/App/Http/Controllers/ScannerController';

type Candidate = {
    providerId: string;
    name: string;
    type?: string;
    text?: string;
    imageUrl?: string;
    setName?: string;
    ownedQuantity: number;
};
type Result = {
    rawText: string;
    detectedName?: string;
    confidence: number;
    candidates: Candidate[];
};
type Item = {
    id: string;
    file?: File;
    preview?: string;
    status: 'queued' | 'processing' | 'pending' | 'saved' | 'failed';
    scanId?: number;
    result?: Result;
    selected?: string;
    error?: string;
    savedMessage?: string;
};

function token() {
    return document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
}
async function jsonRequest(url: string, init: RequestInit) {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');
    if (token()) headers.set('X-XSRF-TOKEN', decodeURIComponent(token()!));
    const response = await fetch(url, {
        ...init,
        headers,
    });
    const payload = (await response.json()) as Record<string, unknown>;
    if (!response.ok)
        throw new Error(
            (payload.message as string) ||
                'Não foi possível concluir a solicitação.',
        );
    return payload;
}

export default function Scanner() {
    const [items, setItems] = useState<Item[]>([]);
    const [activeId, setActiveId] = useState<string>();
    const [search, setSearch] = useState('');
    const active = items.find((item) => item.id === activeId) ?? items[0];
    const counters = useMemo(
        () => ({
            processed: items.filter(
                (i) => !['queued', 'processing'].includes(i.status),
            ).length,
            saved: items.filter((i) => i.status === 'saved').length,
            pending: items.filter((i) => i.status === 'pending').length,
            unidentified: items.filter((i) => i.status === 'failed').length,
        }),
        [items],
    );
    const update = (id: string, data: Partial<Item>) =>
        setItems((current) =>
            current.map((item) =>
                item.id === id ? { ...item, ...data } : item,
            ),
        );

    async function processItem(item: Item) {
        if (!item.file) return;
        update(item.id, { status: 'processing', error: undefined });
        const body = new FormData();
        body.append('image', item.file);
        try {
            const payload = (await jsonRequest(ScannerController.scan.url(), {
                method: 'POST',
                body,
            })) as unknown as { scan: { id: number }; result: Result };
            update(item.id, {
                status: payload.result.candidates.length ? 'pending' : 'failed',
                scanId: payload.scan.id,
                result: payload.result,
                selected: payload.result.candidates[0]?.providerId,
                error: payload.result.candidates.length
                    ? undefined
                    : 'Nenhuma correspondência foi encontrada.',
            });
        } catch (error) {
            update(item.id, {
                status: 'failed',
                error:
                    error instanceof Error
                        ? error.message
                        : 'Falha ao processar.',
            });
        }
    }
    async function addFiles(event: ChangeEvent<HTMLInputElement>) {
        const added = Array.from(event.target.files ?? []).map((file) => ({
            id: crypto.randomUUID(),
            file,
            preview: URL.createObjectURL(file),
            status: 'queued' as const,
        }));
        setItems((current) => [...current, ...added]);
        if (added[0]) setActiveId(added[0].id);
        event.target.value = '';
        for (const item of added) await processItem(item);
    }
    async function confirm(item: Item) {
        if (!item.scanId || !item.selected) return;
        update(item.id, { status: 'processing', error: undefined });
        try {
            const payload = await jsonRequest(
                ScannerController.confirm.url(item.scanId),
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ provider_id: item.selected }),
                },
            );
            update(item.id, {
                status: 'saved',
                savedMessage: `${payload.message as string} Total: ${payload.totalQuantity as number}.`,
            });
        } catch (error) {
            update(item.id, {
                status: 'pending',
                error:
                    error instanceof Error ? error.message : 'Falha ao salvar.',
            });
        }
    }
    async function manual(event: FormEvent) {
        event.preventDefault();
        if (!search.trim()) return;
        const item: Item = { id: crypto.randomUUID(), status: 'processing' };
        setItems((current) => [...current, item]);
        setActiveId(item.id);
        try {
            const payload = (await jsonRequest(ScannerController.search.url(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: search }),
            })) as unknown as { scan: { id: number }; candidates: Candidate[] };
            update(item.id, {
                scanId: payload.scan.id,
                status: payload.candidates.length ? 'pending' : 'failed',
                result: {
                    rawText: '',
                    detectedName: search,
                    confidence: 0,
                    candidates: payload.candidates,
                },
                selected: payload.candidates[0]?.providerId,
            });
            setSearch('');
        } catch (error) {
            update(item.id, {
                status: 'failed',
                error:
                    error instanceof Error
                        ? error.message
                        : 'Busca indisponível.',
            });
        }
    }
    return (
        <>
            <Head title="Adicionar cartas" />
            <main className="px-5 py-8 sm:px-8">
                <div className="mx-auto max-w-6xl space-y-7">
                    <header className="border-border border-b pb-7">
                        <p className="text-muted-foreground text-xs font-semibold tracking-widest uppercase">
                            Reconhecimento
                        </p>
                        <h1 className="mt-2 text-4xl font-semibold tracking-tight">
                            Adicionar cartas
                        </h1>
                        <p className="text-muted-foreground mt-2">
                            Envie várias imagens. Cada carta fica na fila até
                            você confirmar.
                        </p>
                    </header>
                    <section className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {[
                            ['Processadas', counters.processed],
                            ['Salvas', counters.saved],
                            ['Pendentes', counters.pending],
                            ['Não identificadas', counters.unidentified],
                        ].map(([label, value]) => (
                            <div
                                key={label}
                                className="border-border bg-card border p-4"
                            >
                                <p className="text-muted-foreground text-xs">
                                    {label}
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    {value}
                                </p>
                            </div>
                        ))}
                    </section>
                    <section className="grid gap-4 lg:grid-cols-[300px_1fr]">
                        <aside className="space-y-4">
                            <label className="border-border bg-card hover:bg-muted flex cursor-pointer flex-col items-center border border-dashed p-8 text-center">
                                <ImagePlus />
                                <strong className="mt-3">
                                    Selecionar imagens
                                </strong>
                                <span className="text-muted-foreground mt-1 text-xs">
                                    JPG, PNG ou WebP, até 10 MB cada
                                </span>
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    capture="environment"
                                    onChange={addFiles}
                                    className="sr-only"
                                />
                            </label>
                            <form onSubmit={manual} className="flex gap-2">
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Ou busque pelo nome"
                                    className="border-input bg-background min-w-0 flex-1 border px-3 py-2 text-sm"
                                />
                                <button
                                    className="border-border border px-3"
                                    aria-label="Buscar"
                                >
                                    <Search size={17} />
                                </button>
                            </form>
                            <div className="space-y-2">
                                {items.map((item, index) => (
                                    <button
                                        key={item.id}
                                        onClick={() => setActiveId(item.id)}
                                        className={`flex w-full items-center gap-3 border p-2 text-left ${active?.id === item.id ? 'border-primary' : 'border-border'}`}
                                    >
                                        {item.preview ? (
                                            <img
                                                src={item.preview}
                                                alt=""
                                                className="h-14 w-10 rounded object-cover"
                                            />
                                        ) : (
                                            <Search className="mx-2" />
                                        )}
                                        <span className="min-w-0 flex-1">
                                            <strong className="block truncate text-sm">
                                                {item.result?.detectedName ??
                                                    item.file?.name ??
                                                    `Busca ${index + 1}`}
                                            </strong>
                                            <small className="text-muted-foreground">
                                                {
                                                    {
                                                        queued: 'Na fila',
                                                        processing:
                                                            'Processando…',
                                                        pending: 'Confirmar',
                                                        saved: 'Salva',
                                                        failed: 'Não identificada',
                                                    }[item.status]
                                                }
                                            </small>
                                        </span>
                                        {item.status === 'processing' && (
                                            <LoaderCircle
                                                className="animate-spin"
                                                size={16}
                                            />
                                        )}
                                    </button>
                                ))}
                            </div>
                        </aside>
                        <div className="border-border bg-card min-h-96 border p-5">
                            {!active ? (
                                <div className="flex h-full min-h-80 flex-col items-center justify-center text-center">
                                    <Upload
                                        className="text-muted-foreground"
                                        size={36}
                                    />
                                    <h2 className="mt-4 text-xl font-semibold">
                                        Sua fila está vazia
                                    </h2>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        Selecione uma ou mais fotos para
                                        começar.
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-5">
                                    {active.preview && (
                                        <img
                                            src={active.preview}
                                            alt="Foto enviada"
                                            className="mx-auto max-h-72 rounded object-contain"
                                        />
                                    )}
                                    {active.error && (
                                        <p className="text-destructive text-sm">
                                            {active.error}
                                        </p>
                                    )}
                                    {active.savedMessage && (
                                        <p className="flex items-center gap-2 text-emerald-600">
                                            <Check size={18} />
                                            {active.savedMessage}
                                        </p>
                                    )}
                                    {active.result?.candidates.length ? (
                                        <>
                                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                                {active.result.candidates.map(
                                                    (candidate) => (
                                                        <label
                                                            key={
                                                                candidate.providerId
                                                            }
                                                            className={`cursor-pointer border p-3 ${active.selected === candidate.providerId ? 'border-primary bg-primary/5' : 'border-border'}`}
                                                        >
                                                            <input
                                                                type="radio"
                                                                className="sr-only"
                                                                checked={
                                                                    active.selected ===
                                                                    candidate.providerId
                                                                }
                                                                onChange={() =>
                                                                    update(
                                                                        active.id,
                                                                        {
                                                                            selected:
                                                                                candidate.providerId,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                            {candidate.imageUrl && (
                                                                <img
                                                                    src={
                                                                        candidate.imageUrl
                                                                    }
                                                                    alt=""
                                                                    className="mx-auto mb-3 aspect-[5/7] max-h-60 rounded object-contain"
                                                                />
                                                            )}
                                                            <strong className="block text-sm">
                                                                {candidate.name}
                                                            </strong>
                                                            <span className="text-muted-foreground block text-xs">
                                                                {candidate.setName ??
                                                                    candidate.type}
                                                            </span>
                                                            <span className="text-muted-foreground text-xs">
                                                                Na coleção:{' '}
                                                                {
                                                                    candidate.ownedQuantity
                                                                }
                                                            </span>
                                                        </label>
                                                    ),
                                                )}
                                            </div>
                                            {active.status !== 'saved' && (
                                                <button
                                                    onClick={() =>
                                                        confirm(active)
                                                    }
                                                    disabled={
                                                        active.status ===
                                                        'processing'
                                                    }
                                                    className="bg-primary text-primary-foreground inline-flex items-center gap-2 px-4 py-2 text-sm"
                                                >
                                                    <Check size={16} />{' '}
                                                    Confirmar e salvar
                                                </button>
                                            )}
                                            <details className="text-muted-foreground text-sm">
                                                <summary className="cursor-pointer">
                                                    Detalhes do reconhecimento
                                                </summary>
                                                <p className="mt-2">
                                                    Confiança:{' '}
                                                    {Math.round(
                                                        active.result
                                                            .confidence * 100,
                                                    )}
                                                    %
                                                </p>
                                                <pre className="mt-2 max-h-40 overflow-auto text-xs whitespace-pre-wrap">
                                                    {active.result.rawText}
                                                </pre>
                                            </details>
                                        </>
                                    ) : (
                                        active.status === 'processing' && (
                                            <p className="flex items-center justify-center gap-2 py-20">
                                                <LoaderCircle className="animate-spin" />{' '}
                                                Lendo a carta…
                                            </p>
                                        )
                                    )}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
