<?php

namespace Biigle\Modules\Laserpoints\Http\Requests;

use Biigle\Volume;
use Illuminate\Foundation\Http\FormRequest;

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
            'label_id' => 'required|id|exists:labels,id',
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
            if ($this->volume->hasTiledImages()) {
                $validator->errors()->add('id', 'Laser point detection is not available for volumes with very large images.');
            }
        });
    }
}
