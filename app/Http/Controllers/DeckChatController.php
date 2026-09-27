<?php

namespace App\Http\Controllers;

use App\Services\DeckChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            'context' => ['sometimes', 'array:format,colors,strategy,commander,last_request'],
            'context.format' => ['sometimes', 'string', 'in:commander,standard,modern,pioneer,pauper,legacy,vintage'],
            'context.colors' => ['sometimes', 'array', 'list', 'max:5'],
            'context.colors.*' => ['required', 'string', 'in:W,U,B,R,G', 'distinct'],
            'context.strategy' => ['sometimes', 'string', 'max:60'],
            'context.commander' => ['sometimes', 'integer', 'min:1'],
            'context.last_request' => ['sometimes', 'string', 'in:deck,cards'],
        ], [
            'message.required' => 'Digite uma mensagem.',
            'message.string' => 'A mensagem deve ser um texto.',
            'message.max' => 'A mensagem deve ter no máximo 10000 caracteres.',
        ]);

        try {
            $result = $service->send(
                $request->user(),
                $validated['message'],
                $validated['context'] ?? [],
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Não foi possível obter uma resposta agora. Tente novamente.',
            ], 503);
        }

        return response()->json([...$result, 'context' => (object) $result['context']]);
    }
}
