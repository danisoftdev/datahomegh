<?php

namespace App\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class AfaRegistrationPayload
{
    /**
     * @return array<string, array<int, string|ValidationRule|string>|string>
     */
    public static function innerRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^0[235]\d{8}$/'],
            'ghana_card_number' => ['required', 'string', 'max:50'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'occupation' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{name: string, phone: string, ghana_card_number: string, date_of_birth: string, occupation: string, location: string}
     */
    public static function validateOrFail(?array $payload): array
    {
        $validator = Validator::make($payload ?? [], self::innerRules());
        $validator->setAttributeNames([
            'name' => __('Name'),
            'phone' => __('Number'),
            'ghana_card_number' => __('Ghana Card number'),
            'date_of_birth' => __('Date of birth'),
            'occupation' => __('Occupation'),
            'location' => __('Location'),
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException($validator->errors()->first());
        }

        /** @var array{name: string, phone: string, ghana_card_number: string, date_of_birth: string, occupation: string, location: string} */
        return $validator->validated();
    }

    /**
     * @return array<string, array<int, string|ValidationRule|string>|string>
     */
    public static function nestedRules(string $prefix = 'afa_registration'): array
    {
        $out = [];
        foreach (self::innerRules() as $key => $rules) {
            $out[$prefix.'.'.$key] = $rules;
        }

        return $out;
    }
}
