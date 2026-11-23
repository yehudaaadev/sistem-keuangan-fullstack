<?php

namespace App\Http\Controllers;

use App\Models\Distributor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistributorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $distributor = Distributor::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where('nama', 'like', "%{$s}%"))
            ->orderBy('nama')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($distributor);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Distributor::create($this->validated($request)), 201);
    }

    public function show(Distributor $distributor): JsonResponse
    {
        return response()->json($distributor);
    }

    public function update(Request $request, Distributor $distributor): JsonResponse
    {
        $distributor->update($this->validated($request, true));

        return response()->json($distributor);
    }

    public function destroy(Distributor $distributor): JsonResponse
    {
        $distributor->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'nama' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string'],
        ]);
    }
}
