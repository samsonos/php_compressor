<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>
 * on 19.09.2014 at 17:26
 */
 namespace samsonos\compressor;

/**
 * Compressor form SamsonPHP Event system
 * @author Vitaly Egorov <egorov@samsonos.com>
 * @copyright 2014 SamsonOS
 */
class EventCompressor 
{
    /** @var  array Collection of event subscriptions and handlers */
    public $subscriptions = array();

    /** @var array Collection of event fires */
    public $fires = array();

    protected function & parseSubscription(array & $matches)
    {
        // Resulting collection of arrays
        $events = array();

        // Iterate all matches based on event identifiers
        for($i=0,$l = sizeof($matches['id']); $i < $l; $i++) {
            // Pointer to events collection
            $event = & $events[trim($matches['id'][$i])];
            // Create collection for this event identifier
            $event = isset($event) ? $event : array();
            // Get handler code
            $handler = rtrim(trim($matches['handler'][$i]), ')');
            // Add ')' if this is an array callback
            $handler = stripos($handler, 'array') !== false ? $handler.')' : $handler;
            // Add event callback
            $event[] = array('callback' => $handler);
        }

        return $events;
    }

    /**
     * Find and gather all static event subscription calls
     * @param $code PHP code for searching
     *
     * @return array Collection event subscription collection
     */
    public function findAllStaticSubscriptions($code)
    {
        // Found collection
        $matches = array();

        // Matching pattern
        $pattern = '/\s*>\s*Event::subscribe\s*\(\s*(\'|\")(?<id>[^\'\"]+)(\'|\")\s*,\s*(?<handler>[^;-]+)/';

        // Perform text search
        if (preg_match_all($pattern, $code, $matches)) {
            // Additional handling
        }

        // Call generic subscription parser
        return $this->parseSubscription($matches);
    }

    /**
     * Find all event fire calls in code
     */
    public function findAllFires($code)
    {

    }

    /**
     * Find and gather all dynamic event subscription calls
     * @param $code PHP code for searching
     *
     * @return array Collection event subscription collection
     */
    public function findAllDynamicSubscriptions($code)
    {
        // Found collection
        $matches = array();

        // Matching pattern
        $pattern = '/\s*>\s*subscribe\s*\(\s*(\'|\")(?<id>[^\'\"]+)(\'|\")\s*,\s*(?<handler>[^;-]+)/';

        // Perform text search
        if (preg_match_all($pattern, $code, $matches)) {
            // Additional handling
        }

        // Call generic subscription parser
        return $this->parseSubscription($matches);
    }

    public function transform($input, & $output = '')
    {
        // Gather all events
        $this->subscriptions = array_merge(
            $this->subscriptions,
            $this->findAllDynamicSubscriptions($input),
            $this->findAllStaticSubscriptions($input)
        );

        // Iterate all events
        foreach ($this->events as $id => $event) {

        }
    }
}
 