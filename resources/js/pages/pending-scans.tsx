import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, Check, ImageOff, Search, Trash2 } from 'lucide-react';
import PendingScanController from '@/actions/App/Http/Controllers/PendingScanController';
import ScannerController from '@/actions/App/Http/Controllers/ScannerController';

type Candidate = {
    providerId: string;
    name: string;
    type?: string;
    imageUrl?: string;
    setName?: string;
};
type Scan = {
    id: number;
    original_image_path: string | null;
    detected_name: string | null;
    confidence: number | null;
    candidates: Candidate[] | null;
    status: string;
    created_at: string;
};
type Page<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

function PendingCard({ scan }: { scan: Scan }) {
    const candidates = scan.candidates ?? [];
    const confirmation = useForm({
        provider_id: candidates[0]?.providerId ?? '',
        return_to: 'pending-scans',
    });
    const search = useForm({ name: scan.detected_name ?? '' });
    const cancellation = useForm({});

    function cancel() {
        if (!window.confirm('Cancelar este scan pendente?')) return;

        cancellation.delete(PendingScanController.destroy.url(scan.id), {
            preserveScroll: true,
        });
    }

    return (
        <article className="border-border bg-card grid overflow-hidden border lg:grid-cols-[240px_1fr]">
            <div className="bg-muted flex min-h-64 items-center justify-center">
                {scan.original_image_path ? (
                    <img
                        src={PendingScanController.image.url(scan.id)}
                        alt="Imagem original da carta"
                        className="h-full max-h-96 w-full object-contain"
                    />
                ) : (
                    <ImageOff className="text-muted-foreground" size={36} />
                )}
            </div>
            <div className="space-y-5 p-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-muted-foreground text-xs font-semibold tracking-widest uppercase">
                            Aguardando confirmação
                        </p>
                        <h2 className="mt-1 text-xl font-semibold">
                            {scan.detected_name ?? 'Carta não identificada'}
                        </h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {new Date(scan.created_at).toLocaleString('pt-BR')}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={cancel}
                        disabled={cancellation.processing}
                        className="text-muted-foreground hover:text-destructive inline-flex shrink-0 items-center gap-2 px-2 py-1 text-sm disabled:opacity-50"
                    >
                        <Trash2 size={16} /> Cancelar
                    </button>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        search.post(PendingScanController.search.url(scan.id), {
                            preserveScroll: true,
                        });
                    }}
                    className="flex gap-2"
                >
                    <input
                        value={search.data.name}
                        onChange={(event) =>
                            search.setData('name', event.target.value)
                        }
                        placeholder="Buscar pelo nome da carta"
                        className="border-input bg-background min-w-0 flex-1 border px-3 py-2 text-sm"
                    />
                    <button
                        disabled={search.processing}
                        className="border-border hover:bg-muted inline-flex items-center gap-2 border px-3 py-2 text-sm"
                    >
                        <Search size={16} /> Buscar
                    </button>
                </form>
                {search.errors.name && (
                    <p className="text-destructive text-sm">
                        {search.errors.name}
                    </p>
                )}

                {candidates.length > 0 ? (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            confirmation.post(
                                ScannerController.confirm.url(scan.id),
                            );
                        }}
                        className="space-y-4"
                    >
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {candidates.map((candidate) => (
                                <label
                                    key={candidate.providerId}
                                    className={`cursor-pointer border p-3 transition ${confirmation.data.provider_id === candidate.providerId ? 'border-primary bg-primary/5' : 'border-border'}`}
                                >
                                    <input
                                        type="radio"
                                        className="sr-only"
                                        name={`candidate-${scan.id}`}
                                        value={candidate.providerId}
                                        checked={
                                            confirmation.data.provider_id ===
                                            candidate.providerId
                                        }
                                        onChange={() =>
                                            confirmation.setData(
                                                'provider_id',
                                                candidate.providerId,
                                            )
                                        }
                                    />
                                    {candidate.imageUrl && (
                                        <img
                                            src={candidate.imageUrl}
                                            alt=""
                                            className="mx-auto mb-3 aspect-[5/7] max-h-48 rounded object-contain"
                                        />
                                    )}
                                    <strong className="block text-sm">
                                        {candidate.name}
                                    </strong>
                                    <span className="text-muted-foreground text-xs">
                                        {candidate.setName ?? candidate.type}
                                    </span>
                                </label>
                            ))}
                        </div>
                        {confirmation.errors.provider_id && (
                            <p className="text-destructive text-sm">
                                {confirmation.errors.provider_id}
                            </p>
                        )}
                        <button
                            disabled={
                                confirmation.processing ||
                                !confirmation.data.provider_id
                            }
                            className="bg-primary text-primary-foreground inline-flex items-center gap-2 px-4 py-2 text-sm font-medium disabled:opacity-50"
                        >
                            <Check size={16} /> Confirmar e salvar
                        </button>
                    </form>
                ) : (
                    <p className="text-muted-foreground flex items-center gap-2 text-sm">
                        <AlertCircle size={16} /> Faça uma busca manual para
                        encontrar a carta.
                    </p>
                )}
            </div>
        </article>
    );
}

export default function PendingScans({ scans }: { scans: Page<Scan> }) {
    return (
        <>
            <Head title="Scans pendentes" />
            <main className="px-5 py-8 sm:px-8">
                <div className="mx-auto max-w-6xl space-y-8">
                    <header className="border-border border-b pb-7">
                        <p className="text-muted-foreground text-xs font-semibold tracking-widest uppercase">
                            Revisão
                        </p>
                        <h1 className="mt-2 text-4xl font-semibold tracking-tight">
                            Scans pendentes
                        </h1>
                        <p className="text-muted-foreground mt-2">
                            Confira a imagem e escolha a correspondência correta
                            antes de salvar.
                        </p>
                    </header>
                    {scans.data.length ? (
                        <section className="space-y-5">
                            {scans.data.map((scan) => (
                                <PendingCard key={scan.id} scan={scan} />
                            ))}
                        </section>
                    ) : (
                        <section className="border-border bg-card border p-10 text-center">
                            <Check className="text-muted-foreground mx-auto mb-3" />
                            <h2 className="font-semibold">Tudo revisado</h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Você não tem scans pendentes.
                            </p>
                        </section>
                    )}
                    {scans.last_page > 1 && (
                        <nav className="flex justify-between text-sm">
                            {scans.prev_page_url ? (
                                <Link href={scans.prev_page_url}>
                                    ← Anterior
                                </Link>
                            ) : (
                                <span />
                            )}
                            {scans.next_page_url && (
                                <Link href={scans.next_page_url}>
                                    Próxima →
                                </Link>
                            )}
                        </nav>
                    )}
                </div>
            </main>
        </>
    );
}
