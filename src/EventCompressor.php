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
        for ($i=0,$l = sizeof($matches['id']); $i < $l; $i++) {
            // Pointer to events collection
            $event = & $events[trim($matches['id'][$i])];
            // Create collection for this event identifier
            $event = isset($event) ? $event : array();
            // Get handler code
            $handler = rtrim(trim($matches['handler'][$i]), ')');
            // Add ')' if this is an array callback
            $handler = stripos($handler, 'array') !== false ? $handler.')' : $handler;

            // Create subscription metadata
            $metadata = array(
                'source' => $matches[0][$i]
            );

            // If this is object method callback - parse it
            $args = array();
            if (preg_match('/\s*array\s*\((?<object>[^,]+)\s*,\s*(\'|\")(?<method>[^\'\"]+)/ui', $handler, $args)) {
                // If this is static
                $metadata['object'] = stripos($args['object'], '::') !== false ? $args['object'] : $args['object'].'->';
                $metadata['method'] = $args['method'];
            } else { //global function
                $metadata['method'] = str_replace(array('"',"'"), '', $handler);
            }

            // Add event callback
            $event[] = $metadata;
        }

        return $events;
    }

    /**
     * Find and gather all static event subscription calls
     * @param string $code PHP code for searching
     *
     * @return array Collection event subscription collection
     */
    public function findAllStaticSubscriptions($code)
    {
        // Found collection
        $matches = array();

        // Matching pattern
        $pattern = '/Event::subscribe\s*\(\s*(\'|\")(?<id>[^\'\"]+)(\'|\")\s*,\s*(?<handler>[^;-]+)/ui';

        // Perform text search
        if (preg_match_all($pattern, $code, $matches)) {
            // Additional handling
        }

        // Call generic subscription parser
        return $this->parseSubscription($matches);
    }

    /**
     * Find all event fire calls in code
     *
     * @param $code
     *
     * @return array
     */
    public function findAllFires($code)
    {
        // Resulting collection of arrays
        $events = array();

        // Found collection
        $matches = array();

        // Matching pattern
        $pattern = '/Event::fire\s*\(\s*(\'|\")(?<id>[^\'\"]+)(\'|\")\s*(,\s*(?<params>[^;]+))?/ui';

        // Perform text search
        if (preg_match_all($pattern, $code, $matches)) {
            // Iterate all matches based on event identifiers
            for ($i=0,$l = sizeof($matches['id']); $i < $l; $i++) {
                // Get handler code
                $params = trim($matches['params'][$i]);

                // If this is signal fire - remove last 'true' parameter
                $match = array();
                if (preg_match('/\),\s*true\s*\)/', $params, $match)) {
                    $params = str_replace($match[0], '', $params);

                }

                // Parse all fire parameters
                $args = array();
                if (preg_match('/\s*array\s*\((?<parameters>[^;]+)/ui', $params, $args)) {
                    // Remove reference symbol as we do not need it
                    $params = array();
                    foreach (explode(',', $args['parameters']) as $parameter) {
                        $params[] = str_replace(array('))', '&'), '', $parameter);
                    }
                }

                // Add event callback
                $events[trim($matches['id'][$i])] = array(
                    'params' => $params,
                    'source' => $matches[0][$i]
                );
            }
        }

        return $events;
    }

    /**
     * Find and gather all dynamic event subscription calls
     * @param string $code PHP code for searching
     *
     * @return array Collection event subscription collection
     */
    public function findAllDynamicSubscriptions($code)
    {
        // Found collection
        $matches = array();

        // Matching pattern
        $pattern = '/\s*>\s*subscribe\s*\(\s*(\'|\")(?<id>[^\'\"]+)(\'|\")\s*,\s*(?<handler>[^;-]+)/ui';

        // Perform text search
        if (preg_match_all($pattern, $code, $matches)) {
            // Additional handling
        }

        // Call generic subscription parser
        return $this->parseSubscription($matches);
    }

    /**
     * Analyze code and gather event system calls
     * @param string $input PHP code for analyzing
     */
    public function collect($input)
    {
        // Gather all subscriptions
        $this->subscriptions = array_merge_recursive(
            $this->subscriptions,
            $this->findAllDynamicSubscriptions($input),
            $this->findAllStaticSubscriptions($input)
        );

        // Gather events fires
        $this->fires = array_merge_recursive(
            $this->fires,
            $this->findAllFires($input)
        );
    }

    public function transform($input, & $output = '')
    {
        // Gather everything again
        //$this->collect($input);

        foreach ($this->fires as $id => $data) {
            trace($id);

            // Set pointer to event subscriptions collection
            $subscriptions = & $this->subscriptions[$id];
            if (isset($subscriptions)) {
                // Iterate event subscriptions
                foreach ($subscriptions as $event) {

                    $replace = isset($event['object']) ? $event['object'] : '';
                    $replace .= $event['method'].'(';
                    $replace .= implode(', ', $data['params']).');';

                    trace($replace);
                }
            }
        }

        // Copy output
        $output = $input;

        return true;
    }
}
