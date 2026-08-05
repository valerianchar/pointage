<?php

namespace App\Http\Resources;

use App\Models\BugReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BugReport
 */
class BugReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            // « 28 juil. » — le format court de la maquette.
            'date_label' => $this->created_at->translatedFormat('j M'),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
        ];
    }
}
