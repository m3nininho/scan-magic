import { Head, Link, usePage } from '@inertiajs/react';
import { Library, ScanLine, Sparkles } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;
    return (
        <>
            <Head title="Sua coleção de Magic, organizada" />
            <main className="bg-background text-foreground min-h-screen">
                <nav className="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
                    <span className="flex items-center gap-3 font-semibold">
                        <span className="bg-primary text-primary-foreground flex size-9 items-center justify-center rounded-lg">
                            <AppLogoIcon className="size-5" />
                        </span>
                        Scan Magic
                    </span>
                    <div className="flex gap-3">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="bg-primary text-primary-foreground px-4 py-2 text-sm"
                            >
                                Abrir coleção
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="px-4 py-2 text-sm"
                                >
                                    Entrar
                                </Link>
                                <Link
                                    href={register()}
                                    className="bg-primary text-primary-foreground px-4 py-2 text-sm"
                                >
                                    Criar conta
                                </Link>
                            </>
                        )}
                    </div>
                </nav>
                <section className="mx-auto grid min-h-[75vh] max-w-6xl items-center gap-12 px-6 py-16 lg:grid-cols-2">
                    <div>
                        <p className="text-muted-foreground text-xs font-semibold tracking-[.2em] uppercase">
                            Coleção pessoal de Magic
                        </p>
                        <h1 className="mt-5 text-5xl leading-tight font-semibold tracking-tight sm:text-6xl">
                            Suas cartas, fáceis de encontrar.
                        </h1>
                        <p className="text-muted-foreground mt-6 max-w-xl text-lg">
                            Reconheça cartas por imagem, confirme cada resultado
                            e mantenha sua biblioteca organizada em um só lugar.
                        </p>
                        <Link
                            href={auth.user ? dashboard() : register()}
                            className="bg-primary text-primary-foreground mt-8 inline-flex items-center gap-2 px-5 py-3 font-medium"
                        >
                            Começar agora <span>→</span>
                        </Link>
                    </div>
                    <div className="border-border bg-card grid gap-px border sm:grid-cols-2">
                        <div className="bg-background p-8 sm:col-span-2">
                            <ScanLine />
                            <h2 className="mt-6 text-2xl font-semibold">
                                Adicione várias cartas
                            </h2>
                            <p className="text-muted-foreground mt-2">
                                Envie fotos, acompanhe a fila e revise as
                                correspondências.
                            </p>
                        </div>
                        <div className="bg-background p-8">
                            <Library />
                            <h2 className="mt-5 font-semibold">
                                Biblioteca visual
                            </h2>
                            <p className="text-muted-foreground mt-2 text-sm">
                                Filtros rápidos e detalhes completos.
                            </p>
                        </div>
                        <div className="bg-background p-8">
                            <Sparkles />
                            <h2 className="mt-5 font-semibold">
                                Controle de cópias
                            </h2>
                            <p className="text-muted-foreground mt-2 text-sm">
                                Quantidades sempre atualizadas.
                            </p>
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}
