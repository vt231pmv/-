<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Services\PaginationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClientController extends Controller
{
    public function index(Request $request, PaginationService $paginationService): JsonResponse
    {
        $filters = $request->all();

        /** @var User|null $user */
        $user = Auth::user();
        if ($user && $user->role === 'client' && $user->client_id) {
            $filters['id'] = $user->client_id;
        }

        $query = Client::filter($filters)->with(['bankAccounts', 'credits']);
        $result = $paginationService->paginate($query, $request);

        return response()->json($result, Response::HTTP_OK);
    }

    public function show(string|int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user && $user->role === 'client' && $user->client_id && (int)$id !== (int)$user->client_id) {
            return response()->json(['error' => 'Доступ заборонено'], Response::HTTP_FORBIDDEN);
        }

        $client = Client::with(['bankAccounts', 'credits'])->find($id);

        if (!$client) {
            return response()->json(['error' => 'Клієнта не знайдено'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $client], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:180|unique:clients,email',
            'phone' => 'required|string|max:50',
            'tax_id' => 'required|string|max:50|unique:clients,tax_id',
        ]);

        $client = Client::create($validated);

        return response()->json([
            'data' => $client,
            'message' => 'Клієнта успішно створено',
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, string|int $id): JsonResponse
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json(['error' => 'Клієнта не знайдено'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|max:180|unique:clients,email,' . $client->id,
            'phone' => 'sometimes|string|max:50',
            'tax_id' => 'sometimes|string|max:50|unique:clients,tax_id,' . $client->id,
        ]);

        $client->update($validated);

        return response()->json([
            'data' => $client,
            'message' => 'Дані клієнта успішно оновлено',
        ], Response::HTTP_OK);
    }

    public function destroy(string|int $id): JsonResponse
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json(['error' => 'Клієнта не знайдено'], Response::HTTP_NOT_FOUND);
        }

        try {
            $client->delete();
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Неможливо видалити клієнта через обмеження зовнішнього ключа в базі даних'
            ], Response::HTTP_CONFLICT);
        }

        return response()->json(['message' => 'Клієнта успішно видалено'], Response::HTTP_OK);
    }
}
