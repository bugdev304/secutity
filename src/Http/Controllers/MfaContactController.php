<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Controllers;

use Ae3\AuthSecurity\Contracts\MfaContactProvider;
use Ae3\AuthSecurity\Data\MfaContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MfaContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $contacts = $user instanceof MfaContactProvider
            ? $user->mfaContacts()
            : [];

        return response()->json([
            'data' => array_map(
                fn (MfaContact $contact) => [
                    'channel' => $contact->channel,
                    'identifier' => $contact->identifier,
                    'label' => $contact->label,
                ],
                $contacts,
            ),
            'meta' => [],
        ]);
    }
}
