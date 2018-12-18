<?php

namespace SilverStripe\TestSession;

use SilverStripe\ORM\DataObject;


/**
 * The session state keeps some metadata about the current test session.
 * This may allow the client (Behat) to get some insight into the
 * server side affairs (e.g. if the server is handling some number requests at the moment).
 *
 * The client side (Behat) must not use this class straightforwardly, but rather
 * rely on the API of {@see TestSessionEnvironment} or {@see TestSessionController}.
 */
class TestSessionState extends DataObject
{
    private static $db = [
        /**
         * Pending requests to keep information
         * about how many requests are in progress
         * on the server
         */
        'PendingRequests' => 'Int',

        /**
         * The microtime stamp of the last response
         * made by the server.
         * (well, actually that's rather TestSessionMiddleware)
         */
        'LastResponseTimestamp' => 'Decimal(14, 0)'
    ];
}
