<?php
namespace ModxMcp\Tools;

class LexiconEntryListTool implements ToolInterface
{
    public function name() { return 'list_lexicon_entries'; }
    public function group() { return 'lexicon'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return ProcessorSupport::run(
            $context, 'workspace/lexicon/getlist', $data, true,
            array('core:default', 'core:workspaces')
        );
    }
}
