<?php

namespace Symbiote\Symbiotic\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use SilverStripe\Forms\TextField;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;


class AuthCredential extends DataObject
{
    private static $table_name = 'AuthCredential';

    private static $db = [
        'Salt' => 'Varchar(128)',
        'Hashed' => 'Varchar(128)',
        'Token' => 'Text',
    ];

    private static $has_one = [
        'Member' => Member::class,
        'Organisation' => Organisation::class
    ];

    private static $summary_fields = [
        'Member.Title',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName('Salt');
        $fields->removeByName('Hashed');

        $id = $this->OrganisationID;

        if ($id) {
            if (!$this->MemberID) {
                $members = $this->Organisation()->Members();
                $fields->dataFieldByName('MemberID')->setSource($members);
                $fields->removeByName('Token');
            } else {
                $fields->makeFieldReadonly('Token');
                $fields->makeFieldReadonly('MemberID');
            }
        }

        if ($this->MemberID && $this->Token) {
            $tokenField = TextField::create('JWT', 'JWT token', $this->getJWT())
                ->setReadOnly(true)
                ->setRightTitle("Set this as your Authorization: Bearer {token} header");

            $fields->addFieldToTab('Root.Main', $tokenField);
        }

        return $fields;
    }

    public function getJWT()
    {
        $createdStamp = strtotime($this->Created);
        $signer = new Sha256();
        $jwt = (new Builder())
            // ->setIssuer('https://www.symbiote.com.au') // Configures the issuer (iss claim)
            // ->setIssuedAt($createdStamp) // Configures the time that the token was issued (iat claim)
            // ->setNotBefore($createdStamp + 60) // Configures the time that the token can be used (nbf claim)
            ->setExpiration($createdStamp + 20 * 365 * 86400) // 10 years in the future
            ->set('uid', $this->MemberID) // Configures a new claim, called "uid"
            ->set('orgid', $this->OrganisationID)
            ->sign($signer, $this->Token)
            ->getToken(); // Retrieves the generated token

        return $jwt;
    }

    public function onBeforeWrite()
    {
        if ((!$this->Salt || !$this->Token) && $this->MemberID) {
            $token = bin2hex(random_bytes(48));
            $details = Security::encrypt_password($this->MemberID . $token);
            $this->Salt = $details['salt'];
            $this->Hashed = $details['password'];
            $this->Token = $token;
        }
        parent::onBeforeWrite();
    }

    /**
     * Only admins can manage these for now
     */
    public function canView($member = null)
    {
        return Permission::check('ADMIN');
    }
}
