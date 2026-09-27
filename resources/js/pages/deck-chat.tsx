import { Head } from '@inertiajs/react';
import { MessageSquare, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { send } from '@/actions/App/Http/Controllers/DeckChatController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Message = { id: string; role: 'user' | 'assistant'; content: string };

export default function DeckChat() {
    const [messages, setMessages] = useState<Message[]>([]);
    const [draft, setDraft] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string>();
    const pending = useRef(false);
    const history = useRef<HTMLDivElement>(null);
    const input = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        history.current?.scrollTo({ top: history.current.scrollHeight });
    }, [messages, processing]);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const message = draft.trim();
        if (!message || pending.current) return;

        pending.current = true;
        setProcessing(true);
        setError(undefined);
        const id = crypto.randomUUID();
        setMessages((current) => [
            ...current,
            { id, role: 'user', content: message },
        ]);

        try {
            const token = document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.split('=')[1];
            const response = await fetch(send.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token
                        ? { 'X-XSRF-TOKEN': decodeURIComponent(token) }
                        : {}),
                },
                body: JSON.stringify({ message }),
            });

            if (response.status === 401 || response.status === 419) {
                throw new Error(
                    'Sua sessão expirou. Atualize a página para entrar novamente.',
                );
            }
            if (response.status === 429) {
                throw new Error(
                    'Aguarde um minuto antes de enviar outra mensagem.',
                );
            }
            if (response.status === 422) {
                const payload = await response.json();
                throw new Error(
                    payload.errors?.message?.[0] ?? 'Verifique sua mensagem.',
                );
            }
            if (!response.ok) {
                throw new Error(
                    'Não foi possível obter uma resposta agora. Tente novamente.',
                );
            }

            const payload: { message: string } = await response.json();
            setMessages((current) => [
                ...current,
                {
                    id: crypto.randomUUID(),
                    role: 'assistant',
                    content: payload.message,
                },
            ]);
            setDraft('');
        } catch (exception) {
            setMessages((current) => current.filter((item) => item.id !== id));
            setError(
                exception instanceof Error && !(exception instanceof TypeError)
                    ? exception.message
                    : 'Não foi possível enviar a mensagem. Verifique sua conexão e tente novamente.',
            );
        } finally {
            pending.current = false;
            setProcessing(false);
            input.current?.focus();
        }
    }

    return (
        <>
            <Head title="Chat Magic" />
            <main className="flex h-[calc(100dvh-5rem)] min-h-96 flex-col px-5 py-5 sm:px-8 sm:py-8">
                <div className="mx-auto flex min-h-0 w-full max-w-4xl flex-1 flex-col gap-4">
                    <header className="border-border shrink-0 border-b pb-4">
                        <p className="text-muted-foreground text-xs font-semibold tracking-widest uppercase">
                            Magic: The Gathering
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                            Chat Magic
                        </h1>
                    </header>
                    <div
                        ref={history}
                        role="log"
                        aria-label="Histórico de mensagens"
                        aria-live="polite"
                        className="min-h-0 flex-1 space-y-4 overflow-y-auto py-2"
                    >
                        {messages.length === 0 && (
                            <div className="text-muted-foreground flex h-full flex-col items-center justify-center gap-3 text-center">
                                <MessageSquare
                                    className="size-8"
                                    aria-hidden="true"
                                />
                                <p>O que você quer saber sobre Magic?</p>
                            </div>
                        )}
                        {messages.map((message) => (
                            <article
                                key={message.id}
                                className={cn(
                                    'flex',
                                    message.role === 'user'
                                        ? 'justify-end'
                                        : 'justify-start',
                                )}
                            >
                                <div
                                    className={cn(
                                        'max-w-[90%] rounded-lg px-4 py-3 sm:max-w-[80%]',
                                        message.role === 'user'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'border-border bg-card border',
                                    )}
                                >
                                    <p className="mb-1 text-xs font-semibold">
                                        {message.role === 'user'
                                            ? 'Você'
                                            : 'MagicDeckAgent'}
                                    </p>
                                    <p className="text-sm leading-relaxed wrap-anywhere whitespace-pre-wrap">
                                        {message.content}
                                    </p>
                                </div>
                            </article>
                        ))}
                        {processing && (
                            <div
                                role="status"
                                className="text-muted-foreground flex items-center gap-2 text-sm"
                            >
                                <Spinner aria-hidden="true" /> MagicDeckAgent
                                está respondendo...
                            </div>
                        )}
                    </div>
                    <form
                        onSubmit={submit}
                        className="border-border flex shrink-0 flex-col gap-3 border-t pt-4"
                    >
                        {error && (
                            <Alert>
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        )}
                        <Label htmlFor="message">Sua mensagem</Label>
                        <div className="flex items-end gap-2">
                            <textarea
                                ref={input}
                                id="message"
                                value={draft}
                                onChange={(event) =>
                                    setDraft(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (
                                        event.key === 'Enter' &&
                                        !event.shiftKey &&
                                        !event.nativeEvent.isComposing
                                    ) {
                                        event.preventDefault();
                                        event.currentTarget.form?.requestSubmit();
                                    }
                                }}
                                readOnly={processing}
                                maxLength={10000}
                                rows={2}
                                placeholder="Pergunte sobre Magic..."
                                aria-describedby="message-hint"
                                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-w-0 flex-1 resize-none rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:ring-[3px] md:text-sm"
                            />
                            <Button
                                type="submit"
                                disabled={processing || !draft.trim()}
                            >
                                <Send aria-hidden="true" />
                                <span>Enviar</span>
                            </Button>
                        </div>
                        <p
                            id="message-hint"
                            className="text-muted-foreground text-xs"
                        >
                            Enter envia · Shift+Enter quebra linha
                        </p>
                    </form>
                </div>
            </main>
        </>
    );
}
