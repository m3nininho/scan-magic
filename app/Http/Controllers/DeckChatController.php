<?php

namespace App\Http\Controllers;

use App\Services\DeckChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DeckChatController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('deck-chat');
    }

    public function send(Request $request, DeckChatService $service): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
        ], [
            'message.required' => 'Digite uma mensagem.',
            'message.string' => 'A mensagem deve ser um texto.',
            'message.max' => 'A mensagem deve ter no máximo 10000 caracteres.',
        ]);

        try {
            $message = $service->send(
                $request->user(),
                $validated['message']
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Não foi possível obter uma resposta agora. Tente novamente.',
            ], 503);
        }

        return response()->json(['message' => $message]);
    }
}
