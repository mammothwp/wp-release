<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Deploy;

/**
 * Where a plugin version stands on wordpress.org.
 */
enum WporgState: string
{
    case NotApproved = 'not-approved';
    case Released = 'released';
    case Pending = 'pending';
}
