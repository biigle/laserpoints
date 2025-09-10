<?php

namespace Biigle\Modules\Laserpoints\Http\Requests;

use Biigle\Volume;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class ComputeVolume extends FormRequest
{
    /**
     * The volume that should be processed with laser point detection.
     *
     * @var Volume
     */
    public $volume;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $this->volume = Volume::findOrFail($this->route('id'));

        return $this->user()->can('edit-in', $this->volume);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'distance' => 'required|numeric|min:1',
            'label_id' => 'nullable|integer|exists:labels,id',
            'use_line_detection' => 'boolean',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$this->volume->isImageVolume()) {
                $validator->errors()->add('id', 'Laser point detection is only available for image volumes.');
            }

            if ($this->volume->hasTiledImages()) {
                $validator->errors()->add('id', 'Laser point detection is not available for volumes with very large images.');
            }

            // Check if the selected label is available to the volume's projects
            if ($this->filled('label_id')) {
                $labelId = $this->input('label_id');
                $available = DB::table('labels')
                    ->join('label_tree_project', 'labels.label_tree_id', '=', 'label_tree_project.label_tree_id')
                    ->join('project_volume', 'label_tree_project.project_id', '=', 'project_volume.project_id')
                    ->where('labels.id', $labelId)
                    ->where('project_volume.volume_id', $this->volume->id)
                    ->exists();

                if (!$available) {
                    $validator->errors()->add('label_id', 'The selected label is not available for this volume.');
                }
            }
        });
    }
}
