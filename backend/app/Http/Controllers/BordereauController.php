<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Services\Bordereau\BordereauParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BordereauController extends Controller
{
    public function __construct(private BordereauParserService $parser) {}

    /**
     * Détecte le format du bordereau sans l'importer.
     */
    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $file = $request->file('file');
        $token = Str::random(32);
        $path = $file->storeAs('bordereau_tmp', $token.'.'.$file->getClientOriginalExtension(), 'local');
        $fullPath = Storage::disk('local')->path($path);

        $format = $this->parser->detectFormat($fullPath);

        return response()->json([
            'success' => true,
            'token' => $token,
            'format' => $format,
            'formats_labels' => [
                'alptis_cot' => 'Alptis — Commissions sur cotisations (COT)',
                'selencia' => 'SELENCIA Patrimoine — Commissions sur encours',
                null => 'Format non reconnu',
            ],
        ]);
    }

    /**
     * Importe un bordereau CSV et retourne le résumé de l'opération.
     */
    public function execute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'bordereau_mois' => 'required|date_format:Y-m',
            'mia_user_id' => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam() ?? Team::first();

        if (! $team) {
            return response()->json(['success' => false, 'message' => 'Aucune équipe trouvée.'], 422);
        }

        $miaUserId = $request->input('mia_user_id', $authUser->id);
        $miaUser = User::find($miaUserId) ?? $authUser;

        $file = $request->file('file');
        $token = Str::random(32);
        $path = $file->storeAs('bordereau_tmp', $token.'.'.$file->getClientOriginalExtension(), 'local');
        $fullPath = Storage::disk('local')->path($path);

        $bordereauMois = $request->input('bordereau_mois'); // Format YYYY-MM

        $result = $this->parser->import($fullPath, $team, $miaUser, $bordereauMois);

        @unlink($fullPath);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Liste les MIAs disponibles pour l'équipe courante.
     */
    public function miaUsers(): JsonResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam() ?? Team::first();

        if (! $team) {
            return response()->json(['success' => false, 'data' => []]);
        }

        $mias = $team->users()
            ->whereHas('roles', fn ($q) => $q->where('name', 'MIA'))
            ->select('users.id', 'users.name', 'users.firstname', 'users.email')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'label' => trim(($u->firstname ?? '').' '.($u->name ?? '')),
                'email' => $u->email,
            ]);

        return response()->json(['success' => true, 'data' => $mias]);
    }
}
