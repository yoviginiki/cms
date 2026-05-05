<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WizardSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'current_step' => $this->current_step,
            'status' => $this->status,
            'provisioned_issue_id' => $this->provisioned_issue_id,
            'step1_brief' => $this->step1_brief,
            'step2_structure' => $this->step2_structure,
            'step3_article_selection' => $this->step3_article_selection,
            'step4_analyses' => $this->step4_analyses,
            'step5_directions' => $this->step5_directions,
            'step6_thumbnails' => $this->step6_thumbnails,
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($m) => [
                'id' => $m->id,
                'step' => $m->step,
                'role' => $m->role,
                'content' => $m->content,
                'artifact_update' => $m->artifact_update,
                'tokens_in' => $m->tokens_in,
                'tokens_out' => $m->tokens_out,
                'created_at' => $m->created_at,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
