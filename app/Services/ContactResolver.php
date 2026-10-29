<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * یکپارچه‌سازی هویت مخاطب (مرحله ۳۱): هر شماره موبایل نرمال‌شده دقیقاً
 * یک مخاطب دارد. ثبت هم‌زمان با یک شماره به کلید یکتا برخورد می‌کند و
 * به همان مخاطب موجود برمی‌گردد.
 */
class ContactResolver
{
    public function forMobile(string $normalizedMobile, ?string $name = null): Contact
    {
        $contact = Contact::where('mobile', $normalizedMobile)->first();

        if (! $contact) {
            try {
                $contact = Contact::create([
                    'mobile' => $normalizedMobile,
                    'name' => $name,
                ]);
            } catch (UniqueConstraintViolationException) {
                $contact = Contact::where('mobile', $normalizedMobile)->firstOrFail();
            }
        }

        if ($name && ! $contact->name) {
            $contact->update(['name' => $name]);
        }

        return $contact;
    }
}
