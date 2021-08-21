<?php

namespace Symbiote\Symbiotic\Middleware;

use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Control\HTTPRequest;
use Lcobucci\JWT\Parser;
use Symbiote\Symbiotic\Model\AuthCredential;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

class OrgAuthMiddleware implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $delegate)
    {
        // check for an X-Auth-Token header and run with that
        if ($token = $request->getHeader('Authorization')) {
            // okay, let's deconvert, and ensure it's true
            if (strpos($token, 'Bearer') === 0) {
                $token = preg_split('/\s+/', $token)[1];

                $jwt = (new Parser())->parse((string) $token);
                $org = $jwt->getClaim('orgid');
                $memberId = $jwt->getClaim('uid');

                if ($org && $memberId) {
                    $credential = AuthCredential::get()->filter([
                        'OrganisationID' => $org,
                        'MemberID'  => $memberId,
                    ])->first();

                    if ($credential) {
                        $signer = new Sha256();
                        if ($jwt->verify($signer, $credential->Token)) {
                            $member = Member::get()->byID($memberId);
                            $member->CurrentOrganisationID = $org;
                            Security::setCurrentUser($member);
                        }
                    }
                }
            }
        }

        $response = $delegate($request);

        return $response;
    }
}