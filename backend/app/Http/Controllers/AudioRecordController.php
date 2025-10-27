<?php

namespace App\Http\Controllers;

use App\Models\AudioRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AudioRecordController extends Controller
{
    /**
     * 🔹 Lister tous les enregistrements audio (avec client associé)
     */
    public function index(): JsonResponse
    {
        $records = AudioRecord::with('client')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($records);
    }

    /**
     * 🔹 Voir le détail d’un enregistrement audio
     */
    public function show(int $id): JsonResponse
    {
        $record = AudioRecord::with('client')->findOrFail($id);

        return response()->json($record);
    }

    /**
     * 🔹 Supprimer un enregistrement audio (et le fichier associé)
     */
    public function destroy(int $id): JsonResponse
    {
        $record = AudioRecord::findOrFail($id);

        // Supprimer le fichier audio du stockage
        if ($record->path && Storage::disk('public')->exists($record->path)) {
            Storage::disk('public')->delete($record->path);
        }

        $record->delete();

        return response()->json(['message' => 'Enregistrement supprimé avec succès.']);
    }
}
