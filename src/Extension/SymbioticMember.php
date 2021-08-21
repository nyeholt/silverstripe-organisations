<?php

namespace Symbiote\Symbiotic\Extension;

use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use Symbiote\Symbiotic\Model\Organisation;



class SymbioticMember extends DataExtension
{
    private static $db = [
        // only used during the registration process
        'OrganisationName' => 'Varchar(128)',
    ];

    private static $has_one = [
        'CurrentOrg' => Organisation::class
    ];

    private static $belongs_many_many = [
        'Organisations' => Organisation::class
    ];

    public function updateMemberFormFields(FieldList $fields)
    {
        if (!$this->owner->CurrentOrgID) {
            $fields->push(TextField::create('OrganisationName', 'Organisation'));
        } else {
            $fields->removeByName('OrganisationName');
        }
        
        // else {
        //     if ($this->owner->CurrentOrgID) {
        //         $title = $this->owner->CurrentOrg()->Title;
        //         $fields->push(
        //             LiteralField::create('OrganisationName', Convert::raw2xml('You are a member of ' . $title))
        //                 ->setValue($title)
        //                 ->setReadonly(true)
        //             );
        //     }
        // }

        $orgs = $fields->dataFieldByName('CurrentOrgID');
        if ($orgs) {
            $source = $this->owner->Organisations()->map()->toArray();
            if (count($source) > 1) {
                $orgs->setSource($source);
            } else {
                $fields->removeByName('CurrentOrgID');
            }
        }
    }

    public function onBeforeWrite()
    {
        /**
         * @var DataObject
         */
        $owner = $this->owner;

        // check whether this member is binding themselves to an existing org 
        // that is _not_ their own
        if (strlen($owner->OrganisationName)) {
            // look it up
            $org = Organisation::get()->filter('Title', $owner->OrganisationName)->first();
            if ($org) {
                // nope
                throw new ValidationException("That organisation already exists");
            } else {
                $org = Organisation::create([
                    'Title' => $owner->OrganisationName
                ]);
                $org->write();
                $owner->CurrentOrgID = $org->ID;
            }

            $owner->OrganisationName = '';
        }
    }

    public function onAfterWrite()
    {
        $org = $this->owner->CurrentOrg();
        if ($org && $org->ID) {
            // make sure we're in its members list
            $members = $org->Members();
            if (!$members->count()) {
                $org->PrimaryAccountID = $this->owner->ID;
                $org->write();
            }
            $exists = $members->find('ID', $this->owner->ID);
            if (!$exists) {
                $org->Members()->add($this->owner);
            }
        }
    }
}
