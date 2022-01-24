<?php

namespace Symbiote\Symbiotic\Extension;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use Symbiote\Symbiotic\Model\Organisation;

class OrgOwned extends DataExtension
{
    private static $db = [
        'RestrictedToOrg' => 'Boolean',
    ];

    private static $has_one = [
        'OwningOrg' => Organisation::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $allOrgs = Organisation::get();

        $allowedOrgs = $allOrgs->filterByCallback(function ($item) {
            return $item->canView();
        });

        $fields->insertBefore('Title', $oo = DropdownField::create('OwningOrgID', 'Owning Organisation', $allowedOrgs->map())->setEmptyString(''));

        $default = Security::getCurrentUser() ? Security::getCurrentUser()->CurrentOrgID : 0;
        $oo->setValue($this->owner->OwningOrgID ? $this->owner->OwningOrgID : $default);

        if (!($this->owner instanceof SiteTree)) {
            $this->updateSettingsFields($fields);
        }
    }

    public function updateSettingsFields(FieldList $fields)
    {
        $fields->addFieldsToTab('Root.Settings', CheckboxField::create('RestrictedToOrg', 'Restrict view to only organisation users'));
    }

    public function onBeforeWrite()
    {
        if (!$this->owner->OwningOrgID) {
            $member = Security::getCurrentUser();
            $org = $member->CurrentOrg();
            if ($org) {
                $this->owner->OwningOrgID = $org->ID;
            }
        }
    }

    public function canView($member)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if ($this->owner->RestrictedToOrg) {
            if ($member) {
                $org = $member->CurrentOrg();
                if ($org) {
                    return $this->owner->OwningOrgID == $org->ID;
                }
            }

            return false;
        }
    }

    public function canEdit($member)
    {
        if (Permission::check('ADMIN')) {
            return true;
        }

        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if ($member) {
            $org = $member->CurrentOrg();
            if ($org) {
                return $this->owner->OwningOrgID == $org->ID;
            }
        }
    }

    public function canDelete($member)
    {
        return $this->canEdit($member);
    }
}
