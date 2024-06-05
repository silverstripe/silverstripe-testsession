<?php

namespace SilverStripe\TestSession;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLUpdate;

/**
 * The session state keeps some metadata about the current test session.
 * This may allow the client (Behat) to get some insight into the
 * server side affairs (e.g. if the server is handling some number requests at the moment).
 *
 * The client side (Behat) must not use this class straightforwardly, but rather
 * rely on the API of {@see TestSessionEnvironment} or {@see TestSessionController}.
 *
 * @property int PendingRequests keeps information about how many requests are in progress
 * @property float LastResponseTimestamp microtime of the last response made by the server
 */
class TestSessionState extends DataObject
{
    private static $table_name = 'TestSessionState';

    private static $db = [
        'PendingRequests' => 'Int',
        'LastResponseTimestamp' => 'Decimal(14, 0)'
    ];

    /**
     * Increments TestSessionState.PendingRequests number by 1
     * to indicate we have one more request in progress
     */
    public static function incrementState()
    {
        $schema = DataObject::getSchema();

        $update = SQLUpdate::create(sprintf('"%s"', $schema->tableName(TestSessionState::class)))
            ->addWhere(['ID' => 1])
            ->assignSQL('"PendingRequests"', '"PendingRequests" + 1');

        $update->execute();
    }

    /**
     * Decrements TestSessionState.PendingRequests number by 1
     * to indicate we have one more request in progress.
     * Also updates TestSessionState.LastResponseTimestamp
     * to the current timestamp.
     */
    public static function decrementState()
    {
        $schema = DataObject::getSchema();

        $update = SQLUpdate::create(sprintf('"%s"', $schema->tableName(TestSessionState::class)))
                ->addWhere(['ID' => 1])
                ->assignSQL('"PendingRequests"', '"PendingRequests" - 1')
                ->assign('"LastResponseTimestamp"', TestSessionState::millitime());

        $update->execute();
    }

    /**
     * Returns unix timestamp in milliseconds
     *
     * @return float milliseconds since 1970
     */
    public static function millitime()
    {
        return round(microtime(true) * 1000);
    }
}
