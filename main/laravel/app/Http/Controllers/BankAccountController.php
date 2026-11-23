<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\User;
use App\Services\PaginationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BankAccountController extends Controller
{
    public function index(Request $request, PaginationService $paginationService): JsonResponse
    {
        $filters = $request->all();

        /** @var User|null $user */
        $user = Auth::user();
        if ($user && $user->role === 'client' && $user->client_id) {
            $filters['client_id'] = $user->client_id;
        }

        $query = BankAccount::filter($filters)->with('client');
        $result = $paginationService->paginate($query, $request);

        return response()->json($result, Response::HTTP_OK);
    }

    public function show(string|int $id): JsonResponse
    {
        $account = BankAccount::with(['client', 'credits'])->find($id);

        if (!$account) {
            return response()->json(['error' => 'Банківський рахунок не знайдено'], Response::HTTP_NOT_FOUND);
        }

        /** @var User|null $user */
        $user = Auth::user();
        if ($user && $user->role === 'client' && $user->client_id && (int)$account->client_id !== (int)$user->client_id) {
            return response()->json(['error' => 'Доступ заборонено'], Response::HTTP_FORBIDDEN);
        }

        return response()->json(['data' => $account], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'account_number' => 'required|string|max:34|unique:bank_accounts,account_number',
            'balance' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'status' => 'nullable|string|in:active,blocked,closed',
        ]);

        $account = BankAccount::create($validated);

        return response()->json([
            'data' => $account->load('client'),
            'message' => 'Банківський рахунок успішно створено',
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, string|int $id): JsonResponse
    {
        $account = BankAccount::find($id);

        if (!$account) {
            return response()->json(['error' => 'Банківський рахунок не знайдено'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'account_number' => 'sometimes|string|max:34|unique:bank_accounts,account_number,' . $account->id,
            'balance' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'status' => 'sometimes|string|in:active,blocked,closed',
        ]);

        $account->update($validated);

        return response()->json([
            'data' => $account->load('client'),
            'message' => 'Банківський рахунок успішно оновлено',
        ], Response::HTTP_OK);
    }

    public function destroy(string|int $id): JsonResponse
    {
        $account = BankAccount::find($id);

        if (!$account) {
            return response()->json(['error' => 'Банківський рахунок не знайдено'], Response::HTTP_NOT_FOUND);
        }

        // Захист від видалення рахунку, прив'язаного до кредиту (HTTP 409 Conflict замість SQL 500)
        if ($account->credits()->exists()) {
            return response()->json([
                'error' => 'Неможливо видалити банківський рахунок, оскільки до нього прив\'язані оформлені кредити'
            ], Response::HTTP_CONFLICT);
        }

        try {
            $account->delete();
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Неможливо видалити банківський рахунок через обмеження зовнішнього ключа'
            ], Response::HTTP_CONFLICT);
        }

        return response()->json(['message' => 'Банківський рахунок успішно видалено'], Response::HTTP_OK);
    }
}
