<?php
namespace ModxMcp\Tools;

class TvInputTypeListTool implements ToolInterface
{
    public function name() { return 'list_tv_input_types'; }
    public function group() { return 'tv_inputs'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $arguments)
    {
        return self::catalog($context);
    }

    public static function catalog($context)
    {
        $modx = $context->modx();
        $namespaceClass = $context->platform()->className('namespace');
        $g = function ($label, $use, $requires) { return array('label' => $label, 'use' => $use, 'requires' => $requires); };
        $core = array(
            'text'             => $g('Text', 'short single-line text (titles, labels, css class)', array()),
            'textarea'         => $g('Textarea', 'multi-line plain text (no editor)', array()),
            'textareamini'     => $g('Textarea (Mini)', 'a few lines of plain text', array()),
            'rawtext'          => $g('Text (No Filters)', 'single-line text stored raw (HTML/code, not output-filtered)', array()),
            'rawtextarea'      => $g('Textarea (No Filters)', 'multi-line raw text/HTML/code (not filtered)', array()),
            'richtext'         => $g('RichText', 'formatted body content via the WYSIWYG editor', array()),
            'date'             => $g('Date', 'a date / datetime value', array('input_properties.format? (e.g. %Y-%m-%d)')),
            'number'           => $g('Number', 'a numeric value', array('input_properties.min/max/step? (optional)')),
            'email'            => $g('Email', 'an email address', array()),
            'url'              => $g('URL', 'a URL', array()),
            'hidden'           => $g('Hidden', 'a value not shown in the edit form', array()),
            'checkbox'         => $g('Checkbox', 'one or more on/off options', array('elements (Label==value||...) for multiple', 'default_text?')),
            'listbox'          => $g('Listbox (Single-Select)', 'pick ONE value from a fixed list', array('elements (Label==value||...)', 'default_text?')),
            'listbox-multiple' => $g('Listbox (Multi-Select)', 'pick SEVERAL values from a list', array('elements (Label==value||...)')),
            'radio'            => $g('Radio Options', 'pick ONE value shown as radio buttons', array('elements (Label==value||...)', 'default_text?')),
            'image'            => $g('Image', 'pick/upload an image', array('media_source (id)')),
            'file'             => $g('File', 'pick/upload a file', array('media_source (id)')),
            'resourcelist'     => $g('Resource List', 'pick a MODX resource (page) by id', array('input_properties.parents? to limit the tree')),
            'tag'              => $g('Tag', 'comma-separated tags', array()),
            'autotag'          => $g('Auto-Tag', 'tags with auto-complete', array()),
        );

        $custom = array();
        $results = null;
        try {
            $results = $modx->invokeEvent('OnTVInputRenderList');
        } catch (\Exception $e) {
            $results = null;
        } catch (\Throwable $e) {
            $results = null;
        }
        if (is_array($results)) {
            foreach ($results as $res) {
                if (is_array($res)) { $res = implode("\n", $res); }
                foreach (preg_split('/\r\n|\r|\n/', (string) $res) as $line) {
                    $line = trim($line);
                    if ($line === '') { continue; }
                    if (strpos($line, '==') !== false) {
                        list($k, $lbl) = explode('==', $line, 2);
                        $custom[trim($k)] = trim($lbl);
                    } elseif (strpos($line, '/') === false && strpos($line, '\\') === false) {
                        $custom[$line] = $line;
                    }
                }
            }
        }

        if ($modx->getObject($namespaceClass, array('name' => 'migx'))) {
            if (!isset($custom['migx']))   { $custom['migx'] = 'MIGX'; }
            if (!isset($custom['migxdb'])) { $custom['migxdb'] = 'MIGXdb'; }
        }
        if (!isset($custom['colorpicker']) && $modx->getObject($namespaceClass, array('name' => 'colorpicker'))) {
            $custom['colorpicker'] = 'ColorPicker';
        }

        return array('core' => $core, 'custom' => $custom);
    }
}
