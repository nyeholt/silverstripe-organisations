<?php

namespace Symbiote\Symbiotic\Admin;

use SilverStripe\Admin\ModelAdmin;
use Symbiote\Symbiotic\Model\Organisation;
use SilverStripe\Security\PermissionProvider;

class OrganisationAdmin extends ModelAdmin implements PermissionProvider
{
    private static $menu_title = 'Organisations';

    private static $url_segment = 'organisations';

    private static $managed_models = [
        Organisation::class
    ];
}