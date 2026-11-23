<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /** Filter: ?aksi= (awalan, mis. "penjualan"), ?search= (objek), ?dari= & ?sampai=. */
    public function index(Request $request): JsonResponse
    {
        $log = AuditLog::with('user:id,name,role')
            ->when($request->query('aksi'), fn ($q, $a) => $q->where('aksi', 'like', $a.'%'))
            ->when($request->query('search'), fn ($q, $s) => $q->where('objek', 'like', "%{$s}%"))
            ->when($request->query('dari'), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->query('sampai'), fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 25));

        return response()->json($log);
    }
}
