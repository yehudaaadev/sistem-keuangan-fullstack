<?php

namespace App\Http\Controllers;

use App\Models\Opname;
use App\Services\OpnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OpnameController extends Controller
{
    public function __construct(private readonly OpnameService $service) {}

    public function index(Request $request): JsonResponse
    {
        $opname = Opname::with(['user', 'approvedBy'])->withCount(['detail', 'detail as selisih_item' => fn ($q) => $q->where('selisih', '!=', 0)])
            ->latest('id')->paginate((int) $request->query('per_page', 15));

        return response()->json($opname);
    }

    public function show(Opname $opname): JsonResponse
    {
        return response()->json($opname->load('detail.produk', 'detail.bahanBaku', 'user', 'approvedBy'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'catatan' => ['nullable', 'string', 'max:300'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.jenis' => ['required', Rule::in(['bahan', 'produk'])],
            'items.*.id' => ['required', 'integer'],
            'items.*.stok_fisik' => ['required', 'numeric', 'min:0'],
            'items.*.alasan' => ['nullable', 'string', 'max:30'],
        ]);

        return response()->json($this->service->buat($request->user(), $data['catatan'] ?? null, $data['items']), 201);
    }

    public function setujui(Request $request, Opname $opname): JsonResponse
    {
        return response()->json($this->service->setujui($opname, $request->user()));
    }

    public function tolak(Request $request, Opname $opname): JsonResponse
    {
        $data = $request->validate([
            'alasan' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->service->tolak($opname, $request->user(), $data['alasan'] ?? null));
    }
}
