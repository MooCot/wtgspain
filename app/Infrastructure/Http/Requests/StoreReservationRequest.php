<?php

namespace App\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_reference' => ['required', 'string'],
            'customer_name' => ['required', 'string'],
            'customer_email' => ['required', 'email'],
        ];
    }
}
