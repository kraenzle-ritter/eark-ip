<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation;

enum Outcome: string
{
    /** Checked, nothing wrong. */
    case Passed = 'passed';

    /** Checked, at least one violation. */
    case Failed = 'failed';

    /** Nothing in the package the requirement applies to, e.g. no rightsMD. */
    case NotApplicable = 'not_applicable';

    /** The requirement cannot be decided by a program, e.g. CSIP95. */
    case NotTestable = 'not_testable';

    /** This validator does not check the requirement (yet). */
    case NotChecked = 'not_checked';
}
