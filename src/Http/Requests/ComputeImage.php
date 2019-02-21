<?php

namespace Biigle\Modules\Laserpoints\Http\Requests;

use Biigle\Image;
use Illuminate\Foundation\Http\FormRequest;

class ComputeImage extends FormRequest
{
    /**
     * The image that should be processed with laser point detection.
     *
     * @var Image
     */
    public $image;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $this->image = Image::with('volume')->findOrFail($this->route('id'));

        return $this->user()->can('edit-in', $this->image->volume);
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
            'label_id' => 'required|exists:labels,id',
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
            if ($this->image->tiled === true) {
                $validator->errors()->add('id', 'Laser point detection is not available for very large images.');
            }
        });
    }
}
