<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Controllers;

use Ae3\AuthSecurity\Contracts\MfaContactProvider;
use Ae3\AuthSecurity\Data\MfaContact;
use Ae3\AuthSecurity\Support\ContactTokenizer;
use Ae3\AuthSecurity\Support\IdentifierMasker;
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
                    'channel' => $contact->channel->value,
                    'masked_identifier' => IdentifierMasker::mask($contact->identifier),
                    'label' => $contact->label,
                    'contact_token' => ContactTokenizer::generate($contact->channel, $contact->identifier),
                ],
                $contacts,
            ),
            'meta' => [],
        ]);
    }
}
