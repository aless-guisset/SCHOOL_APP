<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'profile'  => ['required', 'in:school_owner'],
        ])->validate();

        return User::create([
            'firstname' => $input['firstname'],
            'lastname'  => $input['lastname'],
            'email'     => $input['email'],
            'password'  => $input['password'],
            'profile'   => $input['profile'],
        ]);
    }
}
