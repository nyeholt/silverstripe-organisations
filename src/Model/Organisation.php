<?php

namespace Symbiote\Symbiotic\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use Symbiote\Interactives\Model\InteractiveClient;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Security;

class Organisation extends DataObject
{
    private static $table_name = 'Organisation';

    private static $db = [
        'Title' => 'Varchar(128)',
        // add addressable ? 
    ];

    private static $indexes = [
        'Title' => [
            'type' => 'UNIQUE',
        ]
    ];

    private static $has_one = [
        'PrimaryAccount' => Member::class,
        'InteractiveClient' => InteractiveClient::class
    ];

    private static $many_many = [
        'Members' => Member::class,
    ];

    private static $has_many = [
        'AuthTokens' => AuthCredential::class,
    ];

    /**
     * Local cache for member lookup
     */
    private $memberMap = null;

    protected function onBeforeWrite()
    {
        if (!$this->Title) {
            throw new ValidationException('Title is required');
        }

        $client = null;
        if (!$this->InteractiveClientID) {
            $client = InteractiveClient::create([
                'Title' => $this->Title,
                'RegenerateKeys' => true,
            ]);
            $client->write();
            $client->publishRecursive();
            $this->InteractiveClientID = $client->ID;
        } else {
            $client = $this->InteractiveClient();
            if ($client->Title != $this->Title) {
                $client->Title = $this->Title;
                $client->write();
                $client->publishRecursive();
            }
        }

        if ($client && $client->ID) {
            $members = $this->Members();
            $client->Members()->removeAll();
            $client->Members()->addMany($members);
        }

        parent::onBeforeWrite();
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $members = $this->Members();

        $fields->dataFieldByName('PrimaryAccountID')->setSource($members);

        $members = $fields->dataFieldByName('Members');

        if ($members) {
            $fields->addFieldToTab('Root.Main', $members);
            $fields->remove('Root.Members');
        }

        if (!Permission::check('ADMIN')) {
            $fields->remove('InteractiveClientID');
        }

        return $fields;
    }


    public function hasMember($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        if (!$member) {
            return false;
        }
        if (!$this->memberMap) {
            $this->memberMap = $this->Members()->map()->toArray();
        }
        
        return isset($this->memberMap[$member->ID]);
        
    }

    public function canView($member = null)
    {
        if (Permission::check('ADMIN')) {
            return true;
        }

        if (!$member) {
            $member = Member::currentUser();
        }

        if (!$member) {
            return false;
        }

        $members = $this->Members();
        $exists = $members->find('ID', $member->ID);

        return $exists && $exists->ID > 0;
    }

    public function canEdit($member = null)
    {
        if (Permission::check('ADMIN')) {
            return true;
        }
        if (!$member) {
            $member = Member::currentUser();
        }

        return $member->ID == $this->PrimaryAccountID;
    }

    public function canDelete($member = null)
    {
        return Permission::check('ADMIN');
    }
}
