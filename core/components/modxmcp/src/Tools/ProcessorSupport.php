<?php
namespace ModxMcp\Tools;

class ProcessorSupport
{
    public static function error($response)
    {
        $error = $response->getMessage();
        if ($response->hasFieldErrors()) {
            foreach ($response->getFieldErrors() as $fieldError) {
                $error .= " | Field '" . $fieldError->field . "': " . $fieldError->message;
            }
        }
        return $error ? $error : 'Unknown error.';
    }

    public static function stripNoiseFields($rows)
    {
        if (!is_array($rows)) { return $rows; }
        $noise = array('password', 'cachepwd', 'salt', 'hash_class', 'remote_data', 'remote_key', 'session_stale', 'sudo');
        foreach ($rows as &$row) {
            if (is_array($row)) {
                foreach ($noise as $key) { unset($row[$key]); }
            }
        }
        unset($row);
        return $rows;
    }

    public static function unwrap($decoded)
    {
        if (is_array($decoded) && array_key_exists('object', $decoded) && array_key_exists('success', $decoded)) {
            $object = $decoded['object'];
            if (!empty($object)) { return $object; }
            return array('success' => !empty($decoded['success']));
        }
        return $decoded;
    }

    public static function normalize($response)
    {
        $raw = $response->getResponse();
        if (is_array($raw)) { return self::unwrap($raw); }
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) { return self::unwrap($decoded); }
        $object = $response->getObject();
        if (!empty($object)) { return $object; }
        return array('success' => true);
    }

    public static function run($context, $processor, array $data, $isList, array $lexicons)
    {
        $props = $data;
        unset($props['action'], $props['elementType']);
        if ($isList && !isset($props['limit'])) { $props['limit'] = 0; }
        if (!empty($lexicons)) {
            call_user_func_array(array($context->modx()->lexicon, 'load'), $lexicons);
        }

        $response = $context->platform()->runProcessor($context->modx(), $processor, $props);
        if (!$response) {
            throw new \ModxMCPClientException('Processor not found or returned nothing: ' . $processor);
        }
        if ($response->isError()) {
            throw new \ModxMCPClientException(self::error($response));
        }

        if ($isList) {
            $decoded = json_decode($response->getResponse(), true);
            return array(
                'total' => isset($decoded['total']) ? (int) $decoded['total'] : 0,
                'results' => self::stripNoiseFields(isset($decoded['results']) ? $decoded['results'] : array()),
            );
        }
        return self::normalize($response);
    }
}
