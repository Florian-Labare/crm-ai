<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AudioRecord API Resource
 *
 * @property-read \App\Models\AudioRecord $resource
 */
class AudioRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'path' => $this->path,
            'transcription' => $this->when($this->status === 'done', $this->transcription),
            'error_message' => $this->when($this->status === 'failed', $this->transcription),
            'processed_at' => $this->processed_at?->toISOString(),
            'client' => new ClientResource($this->whenLoaded('client')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
