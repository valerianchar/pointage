<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTagRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('tags', 'name')->where('account_id', $this->integer('account_id')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Saisissez le nom du tag.',
            'name.unique' => 'Ce tag existe déjà sur ce compte.',
        ];
    }
}
