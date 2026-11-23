<?php

namespace App\Http\Controllers;

use App\Models\Pembeli;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PembeliController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pembeli = Pembeli::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where('nama', 'like', "%{$s}%"))
            ->orderBy('nama')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($pembeli);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Pembeli::create($this->validated($request)), 201);
    }

    public function show(Pembeli $pembeli): JsonResponse
    {
        return response()->json($pembeli);
    }

    public function update(Request $request, Pembeli $pembeli): JsonResponse
    {
        $pembeli->update($this->validated($request, true));

        return response()->json($pembeli);
    }

    public function destroy(Pembeli $pembeli): JsonResponse
    {
        $pembeli->delete();

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
